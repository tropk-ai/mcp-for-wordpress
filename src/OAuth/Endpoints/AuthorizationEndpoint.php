<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth\Endpoints;

use Tropk\Mcp\OAuth\AuthorizationCodes;
use Tropk\Mcp\OAuth\ClientRegistry;
use Tropk\Mcp\OAuth\Scopes;

/**
 * OAuth /authorize handler.
 *
 * Registered as a regular WP front-end URL (rewrite + template_redirect)
 * rather than a REST API route, because the endpoint relies on the
 * WordPress login cookie. REST API URLs at /wp-json/* have specific
 * cookie + nonce semantics that break the wp-login.php → /authorize
 * round-trip, producing a perpetual login loop. Serving it from
 * /tropk-mcp/oauth/authorize as a normal page makes
 * is_user_logged_in() reliable across the redirect.
 *
 * Path: /tropk-mcp/oauth/authorize
 *
 * On approval the user is redirected to the client's redirect_uri with
 * the issued authorization code; on denial the client gets an OAuth
 * error response in the redirect. PKCE S256 is mandatory; the `plain`
 * method is refused per MCP 2025-11-25 guidance.
 */
final class AuthorizationEndpoint {

	private const NONCE_ACTION = 'tropk_mcp_oauth_consent';
	private const QUERY_VAR    = 'tropk_oauth_action';
	public  const URL_PATH     = '/tropk-mcp/oauth/authorize';

	public function register(): void {
		add_action( 'init', [ $this, 'register_rewrite' ] );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_handle' ], 1 );
		add_action( 'rest_api_init', [ $this, 'register_rest_alias' ] );
	}

	/**
	 * Register a REST alias at /wp-json/tropk-mcp/v1/authorize that
	 * 302 redirects to /tropk-mcp/oauth/authorize. The MCP TypeScript SDK
	 * falls back to constructing `<authorization_server>/authorize` when
	 * the AS metadata can't be reached — with our `authorization_servers`
	 * pointing at /wp-json/tropk-mcp/v1/, the fallback URL is REST-shaped
	 * and lands on this handler, which then bounces the browser to the
	 * cookie-aware path. (REST URLs at /wp-json/* don't have the WordPress
	 * login cookie semantics our consent screen needs.)
	 */
	public function register_rest_alias(): void {
		register_rest_route(
			'tropk-mcp/v1',
			'/authorize',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'redirect_to_consent' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function redirect_to_consent( \WP_REST_Request $request ): \WP_REST_Response {
		$query = $request->get_query_params();
		$target = self::url();
		if ( is_array( $query ) && [] !== $query ) {
			$target = add_query_arg( array_map( 'strval', $query ), $target );
		}
		$response = new \WP_REST_Response( null, 302 );
		$response->header( 'Location', $target );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
		return $response;
	}

	public function register_rewrite(): void {
		add_rewrite_rule(
			'^tropk-mcp/oauth/authorize/?$',
			'index.php?' . self::QUERY_VAR . '=authorize',
			'top'
		);
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function maybe_handle(): void {
		if ( get_query_var( self::QUERY_VAR ) !== 'authorize' ) {
			return;
		}
		// Discard any buffered output that other plugins may have started.
		// We need a clean response so wp_redirect() can issue a 302 Location
		// header without "headers already sent" warnings — otherwise the
		// browser would receive the consent HTML again instead of the
		// redirect back to the OAuth client's redirect_uri.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		$method = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
		if ( 'POST' === $method ) {
			$this->handle_post( $_POST );
		} else {
			$this->handle_get( $_GET );
		}
		exit;
	}

	public static function url(): string {
		return home_url( self::URL_PATH );
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private function handle_get( array $params ): void {
		$validated = $this->validate_request( $params );
		if ( $validated instanceof \WP_Error ) {
			$this->emit_error( $validated, $params );
			return;
		}

		if ( ! is_user_logged_in() ) {
			$login = wp_login_url( $this->current_url() );
			wp_safe_redirect( $login );
			exit;
		}

		$this->render_consent( $validated, $params );
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private function handle_post( array $params ): void {
		if ( ! is_user_logged_in() ) {
			$this->emit_error(
				new \WP_Error( 'login_required', __( 'You must be logged in to grant access.', 'mcp-for-wordpress' ), [ 'status' => 401 ] ),
				$params
			);
			return;
		}
		if ( ! wp_verify_nonce( (string) ( $params['_nonce'] ?? '' ), self::NONCE_ACTION ) ) {
			$this->emit_error(
				new \WP_Error( 'csrf_failed', __( 'Consent form expired or invalid.', 'mcp-for-wordpress' ), [ 'status' => 400 ] ),
				$params
			);
			return;
		}

		$validated = $this->validate_request( $params );
		if ( $validated instanceof \WP_Error ) {
			$this->emit_error( $validated, $params );
			return;
		}

		$action = (string) ( $params['decision'] ?? 'deny' );
		if ( 'allow' !== $action ) {
			$this->finish_redirect( $this->append_query( $validated['redirect_uri'], [
				'error'             => 'access_denied',
				'error_description' => 'User denied consent.',
				'state'             => $validated['state'],
			] ) );
			return;
		}

		$code = ( new AuthorizationCodes() )->issue(
			[
				'client_id'      => $validated['client_id'],
				'user_id'        => get_current_user_id(),
				'redirect_uri'   => $validated['redirect_uri'],
				'code_challenge' => $validated['code_challenge'],
				'scope'          => Scopes::serialize( $validated['scopes'] ),
				'resource'       => $validated['resource'],
			]
		);

		$this->finish_redirect( $this->append_query( $validated['redirect_uri'], [
			'code'  => $code,
			'state' => $validated['state'],
		] ) );
	}

	/**
	 * Issue a 302 to the OAuth client's redirect_uri and stop processing.
	 *
	 * Bypasses wp_redirect() — which sends a sanitized Location header but
	 * does NOT exit — and emits both the header AND an HTML fallback so
	 * webview-based OAuth consumers (Cursor, Windsurf) still close their
	 * dialog if the host adds Set-Cookie or other late headers that would
	 * prevent the 302 from being followed.
	 */
	private function finish_redirect( string $url ): void {
		if ( ! headers_sent() ) {
			status_header( 302 );
			header( 'Location: ' . $url, true, 302 );
		}
		// HTML/JS fallback in case headers were already sent or the OAuth
		// client's webview ignores 302s on app-protocol URLs.
		header( 'Content-Type: text/html; charset=utf-8' );
		printf(
			'<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=%s"><title>%s</title></head><body><script>window.location.replace(%s)</script><p>%s <a href="%s">%s</a></p></body></html>',
			esc_attr( $url ),
			esc_html__( 'Redirecting…', 'mcp-for-wordpress' ),
			wp_json_encode( $url ),
			esc_html__( 'Redirecting back to the application…', 'mcp-for-wordpress' ),
			esc_attr( $url ),
			esc_html__( 'Click here if you are not redirected.', 'mcp-for-wordpress' )
		);
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>|\WP_Error
	 */
	private function validate_request( array $params ) {
		$client_id     = (string) ( $params['client_id'] ?? '' );
		$redirect_uri  = (string) ( $params['redirect_uri'] ?? '' );
		$response_type = (string) ( $params['response_type'] ?? '' );
		$code_challenge       = (string) ( $params['code_challenge'] ?? '' );
		$code_challenge_method = strtoupper( (string) ( $params['code_challenge_method'] ?? '' ) );
		$state         = (string) ( $params['state'] ?? '' );
		$resource      = (string) ( $params['resource'] ?? '' );
		$scope_req     = (string) ( $params['scope'] ?? Scopes::READ );

		if ( '' === $client_id ) {
			return new \WP_Error( 'invalid_request', 'client_id is required.', [ 'status' => 400 ] );
		}
		$client = ( new ClientRegistry() )->find( $client_id );
		if ( null === $client ) {
			return new \WP_Error( 'invalid_client', 'Unknown client.', [ 'status' => 401 ] );
		}
		if ( '' === $redirect_uri || ! ( new ClientRegistry() )->allows_redirect( $client, $redirect_uri ) ) {
			return new \WP_Error( 'invalid_request', 'redirect_uri not registered for this client.', [ 'status' => 400 ] );
		}
		if ( 'code' !== $response_type ) {
			return new \WP_Error( 'unsupported_response_type', 'Only response_type=code is supported.', [ 'status' => 400 ] );
		}
		if ( '' === $code_challenge || 'S256' !== $code_challenge_method ) {
			return new \WP_Error( 'invalid_request', 'PKCE S256 challenge is required.', [ 'status' => 400 ] );
		}

		$expected_audience = rest_url( 'tropk-mcp/v1/mcp' );
		if ( '' !== $resource && rtrim( strtolower( $resource ), '/' ) !== rtrim( strtolower( $expected_audience ), '/' ) ) {
			return new \WP_Error( 'invalid_target', 'resource does not match this MCP server.', [ 'status' => 400 ] );
		}
		if ( '' === $resource ) {
			$resource = $expected_audience;
		}

		$requested_scopes = Scopes::parse( $scope_req );
		if ( [] === $requested_scopes ) {
			$requested_scopes = [ Scopes::READ ];
		}
		$allowed_scopes = Scopes::parse( (string) $client['scope'] );
		$granted        = array_values( array_intersect( $requested_scopes, $allowed_scopes ) );
		if ( [] === $granted ) {
			$granted = [ Scopes::READ ];
		}

		return [
			'client'           => $client,
			'client_id'        => $client_id,
			'redirect_uri'     => $redirect_uri,
			'code_challenge'   => $code_challenge,
			'scopes'           => $granted,
			'requested_scopes' => $requested_scopes,
			'state'            => $state,
			'resource'         => $resource,
		];
	}

	/**
	 * @param array<string, mixed> $validated
	 * @param array<string, mixed> $params
	 */
	private function render_consent( array $validated, array $params ): void {
		$user = wp_get_current_user();
		header( 'Content-Type: text/html; charset=utf-8' );

		$client       = $validated['client'];
		$display_scopes = isset( $validated['requested_scopes'] ) && is_array( $validated['requested_scopes'] ) && [] !== $validated['requested_scopes']
			? $validated['requested_scopes']
			: $validated['scopes'];
		$granted_set    = array_flip( (array) $validated['scopes'] );

		// Human-readable label for each scope so non-technical site owners
		// understand exactly what the client is asking for.
		$scope_labels = [
			Scopes::READ        => __( 'Read posts, pages and site data', 'mcp-for-wordpress' ),
			Scopes::WRITE       => __( 'Create and update content', 'mcp-for-wordpress' ),
			Scopes::DESTRUCTIVE => __( 'Delete content (destructive)', 'mcp-for-wordpress' ),
			Scopes::ADMIN       => __( 'Administer the site', 'mcp-for-wordpress' ),
			Scopes::OPENID      => __( 'Identify the WordPress user', 'mcp-for-wordpress' ),
			Scopes::OFFLINE     => __( 'Stay connected when you are offline', 'mcp-for-wordpress' ),
		];

		$scope_chips = '';
		foreach ( $display_scopes as $scope ) {
			$label   = $scope_labels[ $scope ] ?? '';
			$granted = isset( $granted_set[ $scope ] );
			$scope_chips .= sprintf(
				'<li class="%s"><code>%s</code>%s%s</li>',
				$granted ? 'granted' : 'not-granted',
				esc_html( $scope ),
				'' !== $label ? ' — ' . esc_html( $label ) : '',
				$granted ? '' : ' <span class="muted">(' . esc_html__( 'not enabled for this client', 'mcp-for-wordpress' ) . ')</span>'
			);
		}

		$action_url = esc_url( self::url() );
		$nonce      = wp_create_nonce( self::NONCE_ACTION );

		$hidden_inputs = '';
		foreach ( [ 'client_id', 'redirect_uri', 'response_type', 'code_challenge', 'code_challenge_method', 'state', 'resource', 'scope' ] as $field ) {
			$value = (string) ( $params[ $field ] ?? '' );
			$hidden_inputs .= sprintf(
				'<input type="hidden" name="%s" value="%s">',
				esc_attr( $field ),
				esc_attr( $value )
			);
		}

		printf(
			'<!doctype html><html><head><meta charset="utf-8"><title>%s</title>' .
			'<style>body{font-family:system-ui,sans-serif;max-width:560px;margin:80px auto;padding:0 24px;color:#1d2327}h1{font-size:20px}.client{font-weight:600}ul.scopes{list-style:none;padding:0;margin:12px 0}ul.scopes li{background:#f0f0f1;padding:8px 12px;border-radius:6px;margin:6px 0;font-size:13px;line-height:1.5}ul.scopes li.not-granted{background:#fcf0f1;color:#7a3434}ul.scopes li.not-granted code{background:#f5d8d9;color:#7a3434}ul.scopes code{background:#fff;padding:1px 6px;border-radius:3px;font-size:12px}ul.scopes .muted{color:#7a3434;font-size:12px}form{margin-top:24px}button{padding:8px 16px;border-radius:4px;border:1px solid #2271b1;cursor:pointer;font-size:14px}button.primary{background:#2271b1;color:#fff;margin-right:8px}button.secondary{background:#fff;color:#1d2327;border-color:#dcdcde}.user{margin-top:24px;color:#646970;font-size:13px}</style></head><body>' .
			'<h1>%s</h1>' .
			'<p><span class="client">%s</span> %s</p>' .
			'<ul class="scopes">%s</ul>' .
			'<form method="post" action="%s">%s<input type="hidden" name="_nonce" value="%s">' .
			'<button type="submit" name="decision" value="allow" class="primary">%s</button>' .
			'<button type="submit" name="decision" value="deny" class="secondary">%s</button></form>' .
			'<p class="user">%s</p>' .
			'</body></html>',
			esc_html__( 'Authorize MCP client', 'mcp-for-wordpress' ),
			esc_html__( 'Authorize MCP client', 'mcp-for-wordpress' ),
			esc_html( (string) ( $client['client_name'] ?? 'an unnamed client' ) ),
			esc_html__( 'is requesting permission to access your site with the following scopes:', 'mcp-for-wordpress' ),
			$scope_chips, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — items already individually escaped via esc_html in the builder loop.
			$action_url,
			$hidden_inputs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — values escaped via esc_attr in builder loop.
			esc_attr( $nonce ),
			esc_html__( 'Allow', 'mcp-for-wordpress' ),
			esc_html__( 'Deny', 'mcp-for-wordpress' ),
			esc_html( sprintf( __( 'Signed in as %s.', 'mcp-for-wordpress' ), $user instanceof \WP_User ? $user->user_login : '' ) )
		);
	}

	private function current_url(): string {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = (string) ( $_SERVER['HTTP_HOST'] ?? '' );
		$uri    = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
		return $scheme . $host . $uri;
	}

	/**
	 * @param array<string, string> $extra
	 */
	private function append_query( string $url, array $extra ): string {
		$sep = false === strpos( $url, '?' ) ? '?' : '&';
		return $url . $sep . http_build_query( array_filter( $extra, static fn( $v ) => '' !== $v && null !== $v ) );
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private function emit_error( \WP_Error $error, array $params ): void {
		$redirect = (string) ( $params['redirect_uri'] ?? '' );
		$client   = (string) ( $params['client_id'] ?? '' );
		$client_obj = '' === $client ? null : ( new ClientRegistry() )->find( $client );

		if ( '' !== $redirect && $client_obj && ( new ClientRegistry() )->allows_redirect( $client_obj, $redirect ) ) {
			$this->finish_redirect( $this->append_query( $redirect, [
				'error'             => $error->get_error_code(),
				'error_description' => $error->get_error_message(),
				'state'             => (string) ( $params['state'] ?? '' ),
			] ) );
			return;
		}

		$status = (int) ( $error->get_error_data()['status'] ?? 400 );
		status_header( $status );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( $error->get_error_code() . ': ' . $error->get_error_message() );
	}
}
