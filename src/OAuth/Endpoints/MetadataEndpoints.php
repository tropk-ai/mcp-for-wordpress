<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth\Endpoints;

use Tropk\Mcp\OAuth\Scopes;

/**
 * Publishes OAuth 2.1 discovery documents. Two delivery channels are
 * wired up because shared/managed hosts (Cloudflare, cPanel, some
 * NGINX defaults) frequently 404 anything under /.well-known/ before
 * the request reaches WordPress:
 *
 *   1. Canonical REST routes under /wp-json/tropk-mcp/v1/ — always
 *      reachable because /wp-json is the WP REST API entry point.
 *   2. /.well-known/ paths via parse_request — best-effort, used as a
 *      compatibility fallback for clients that hard-code the spec URLs.
 *
 * The WWW-Authenticate header issued by BearerAuthenticator points at
 * the REST URL, so the discovery path used by Claude.ai / ChatGPT is
 * guaranteed to be reachable.
 */
final class MetadataEndpoints {

	public const REST_PRM_PATH = '/oauth-protected-resource';
	public const REST_AS_PATH  = '/oauth-authorization-server';
	private const QUERY_VAR    = 'tropk_well_known';

	public function register(): void {
		add_action( 'parse_request', [ $this, 'maybe_serve_well_known' ], 1 );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'init', [ $this, 'register_rewrites' ] );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'serve_via_rewrite' ], 1 );
	}

	public function register_rewrites(): void {
		add_rewrite_rule( '^\.well-known/oauth-protected-resource/?$', 'index.php?' . self::QUERY_VAR . '=prm', 'top' );
		add_rewrite_rule( '^\.well-known/oauth-protected-resource/(.+)$', 'index.php?' . self::QUERY_VAR . '=prm', 'top' );
		add_rewrite_rule( '^\.well-known/oauth-authorization-server/?$', 'index.php?' . self::QUERY_VAR . '=as', 'top' );
		add_rewrite_rule( '^\.well-known/oauth-authorization-server/(.+)$', 'index.php?' . self::QUERY_VAR . '=as', 'top' );
		// ChatGPT's MCP custom-connector probes /.well-known/openid-configuration
		// directly (not via WWW-Authenticate). Serve the same AS metadata there
		// so ChatGPT can find OAuth. RFC 8414 + OIDC Discovery 1.0 overlap on
		// the fields ChatGPT needs (issuer/token/authorization endpoints).
		add_rewrite_rule( '^\.well-known/openid-configuration/?$', 'index.php?' . self::QUERY_VAR . '=as', 'top' );
		add_rewrite_rule( '^\.well-known/openid-configuration/(.+)$', 'index.php?' . self::QUERY_VAR . '=as', 'top' );

		$flushed = (string) get_option( 'tropk_mcp_rewrites_flushed', '' );
		if ( TROPK_MCP_VERSION !== $flushed ) {
			flush_rewrite_rules( false );
			update_option( 'tropk_mcp_rewrites_flushed', TROPK_MCP_VERSION, false );
		}
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function serve_via_rewrite(): void {
		$kind = get_query_var( self::QUERY_VAR );
		if ( '' === $kind || null === $kind ) {
			return;
		}
		if ( 'prm' === $kind ) {
			$this->respond( $this->protected_resource_metadata() );
		} elseif ( 'as' === $kind ) {
			$this->respond( $this->authorization_server_metadata() );
		}
	}

	public function register_rest_routes(): void {
		register_rest_route(
			'tropk-mcp/v1',
			self::REST_PRM_PATH,
			[
				'methods'             => 'GET',
				'callback'            => function (): \WP_REST_Response {
					$response = new \WP_REST_Response( $this->protected_resource_metadata(), 200 );
					$response->header( 'Cache-Control', 'public, max-age=600' );
					return $response;
				},
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			'tropk-mcp/v1',
			self::REST_AS_PATH,
			[
				'methods'             => 'GET',
				'callback'            => function (): \WP_REST_Response {
					$response = new \WP_REST_Response( $this->authorization_server_metadata(), 200 );
					$response->header( 'Cache-Control', 'public, max-age=600' );
					return $response;
				},
				'permission_callback' => '__return_true',
			]
		);
	}

	public function maybe_serve_well_known(): void {
		$path = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
		$path = (string) wp_parse_url( $path, PHP_URL_PATH );
		if ( '' === $path ) {
			return;
		}

		$base = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		$base = '' === $base ? '' : rtrim( $base, '/' );
		if ( $base !== '' && str_starts_with( $path, $base ) ) {
			$path = substr( $path, strlen( $base ) );
		}

		// Accept both the path-only and RFC 9728 path-appended variants
		// (`/.well-known/oauth-protected-resource/wp-json/tropk-mcp/v1/mcp`).
		if ( '/.well-known/oauth-authorization-server' === $path
			|| str_starts_with( $path, '/.well-known/oauth-authorization-server/' ) ) {
			$this->respond( $this->authorization_server_metadata() );
			return;
		}
		if ( '/.well-known/oauth-protected-resource' === $path
			|| str_starts_with( $path, '/.well-known/oauth-protected-resource/' ) ) {
			$this->respond( $this->protected_resource_metadata() );
			return;
		}
		// ChatGPT custom-connector OAuth discovery probes /.well-known/openid-configuration
		// directly. Serve the same authorization-server metadata there (RFC 8414 ∩ OIDC
		// Discovery share the fields ChatGPT actually needs).
		if ( '/.well-known/openid-configuration' === $path
			|| str_starts_with( $path, '/.well-known/openid-configuration/' ) ) {
			$this->respond( $this->authorization_server_metadata() );
			return;
		}
	}

	public static function rest_prm_url(): string {
		return rest_url( 'tropk-mcp/v1' . self::REST_PRM_PATH );
	}

	public static function rest_as_url(): string {
		return rest_url( 'tropk-mcp/v1' . self::REST_AS_PATH );
	}

	/**
	 * Canonical PRM URL on the /.well-known path. ChatGPT and some other
	 * clients only accept the well-known form in WWW-Authenticate.
	 */
	public static function well_known_prm_url(): string {
		return home_url( '/.well-known/oauth-protected-resource' );
	}

	public static function well_known_as_url(): string {
		return home_url( '/.well-known/oauth-authorization-server' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function authorization_server_metadata(): array {
		return [
			'issuer'                                => home_url( '/' ),
			'authorization_endpoint'                => AuthorizationEndpoint::url(),
			'token_endpoint'                        => rest_url( 'tropk-mcp/v1/oauth/token' ),
			'registration_endpoint'                 => rest_url( 'tropk-mcp/v1/oauth/register' ),
			'revocation_endpoint'                   => rest_url( 'tropk-mcp/v1/oauth/revoke' ),
			'response_types_supported'              => [ 'code' ],
			'response_modes_supported'              => [ 'query' ],
			'grant_types_supported'                 => [ 'authorization_code', 'refresh_token' ],
			'code_challenge_methods_supported'      => [ 'S256' ],
			'token_endpoint_auth_methods_supported' => [ 'none', 'client_secret_basic', 'client_secret_post' ],
			'scopes_supported'                      => Scopes::ALL,
			// Minimal OIDC-Discovery-compatible fields. ChatGPT's MCP custom-connector
			// probes /.well-known/openid-configuration in addition to RFC 8414 and
			// rejects the server as "does not implement OAuth" if either response
			// looks malformed. We don't actually issue ID tokens, but advertising the
			// minimum OIDC field set keeps strict probers happy.
			'subject_types_supported'               => [ 'public' ],
			'id_token_signing_alg_values_supported' => [ 'none' ],
			'service_documentation'                 => 'https://github.com/tropk-ai/mcp-for-wordpress',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function protected_resource_metadata(): array {
		return [
			'resource'                  => rest_url( 'tropk-mcp/v1/mcp' ),
			'authorization_servers'     => [ home_url( '/' ) ],
			'authorization_server_metadata_endpoint' => self::rest_as_url(),
			'scopes_supported'          => Scopes::ALL,
			'bearer_methods_supported'  => [ 'header' ],
			'resource_documentation'    => 'https://github.com/tropk/tropk',
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function respond( array $payload ): void {
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Cache-Control: public, max-age=600' );
		echo wp_json_encode( $payload );
		exit;
	}
}
