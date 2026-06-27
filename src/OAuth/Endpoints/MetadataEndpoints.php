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

	/**
	 * Earliest possible interception of /.well-known/ paths — called from
	 * the plugin bootstrap before the main Plugin class boots, so it runs
	 * even if the rest of the plugin fails to initialize.
	 *
	 * Many Brazilian shared hosts (Hostinger HCDN, KingHost-style nginx,
	 * some LiteSpeed defaults) leak through to WordPress when /.well-known/
	 * isn't served from disk, and WP's `redirect_canonical` fires before
	 * our `parse_request` listener — turning a perfectly good discovery
	 * request into a 301 to the homepage (`x-redirect-by: WordPress`).
	 *
	 * `plugins_loaded:1` runs before `init`, `parse_request`, and
	 * `template_redirect`, so we can match REQUEST_URI directly and
	 * `exit` before any WordPress routing decision has been made.
	 */
	public static function boot_well_known_early(): void {
		add_action( 'plugins_loaded', [ self::class, 'maybe_serve_well_known_early' ], 1 );
	}

	public function register(): void {
		add_action( 'parse_request', [ $this, 'maybe_serve_well_known' ], 1 );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'init', [ $this, 'register_rewrites' ] );
		// Flush at init:999 so every other plugin component (notably
		// AuthorizationEndpoint at init:10) has already called
		// add_rewrite_rule(). Flushing inside register_rewrites() at init:10
		// raced AuthorizationEndpoint's '^tropk-mcp/oauth/authorize/?$' rule
		// — it was added AFTER the flush, never persisted to the rewrite_rules
		// option, and the /authorize URL 404'd until the next manual flush.
		add_action( 'init', [ $this, 'maybe_flush_rewrites' ], 999 );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'serve_via_rewrite' ], 1 );
	}

	/**
	 * Static, dependency-free handler invoked from `plugins_loaded:1`.
	 * Intentionally avoids the WP REST API stack and any helper that
	 * requires the main Plugin class — it has to work even when the
	 * plugin's normal bootstrap path is broken.
	 *
	 * Bails out cleanly when `$wp_rewrite` is still null at plugins_loaded:1
	 * (some hosts initialise it later in the request lifecycle). Without
	 * this guard `rest_url()` calls `using_index_permalinks()` on null and
	 * fatals — taking down ability registration for the whole request
	 * (which is what made abvcap.com.br lose ~250 abilities on 0.5.18+).
	 */
	public static function maybe_serve_well_known_early(): void {
		$path = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
		if ( '' === $path ) {
			return;
		}
		$path = (string) wp_parse_url( $path, PHP_URL_PATH );
		if ( '' === $path ) {
			return;
		}

		// Strip the home_url() path component so subdirectory installs
		// (`/blog/.well-known/openid-configuration`) still match.
		$base = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		$base = '' === $base ? '' : rtrim( $base, '/' );
		if ( '' !== $base && str_starts_with( $path, $base ) ) {
			$path = substr( $path, strlen( $base ) );
		}

		if ( ! preg_match(
			'#^/\.well-known/(oauth-protected-resource|oauth-authorization-server|openid-configuration)(?:/|$)#',
			$path,
			$matches
		) ) {
			return;
		}

		// rest_url() depends on $wp_rewrite — on hosts that initialize the
		// rewrite globals later in the request lifecycle, calling it from
		// plugins_loaded:1 throws a fatal. Defer to the parse_request /
		// rewrite paths, which always have $wp_rewrite ready.
		global $wp_rewrite;
		if ( ! ( $wp_rewrite instanceof \WP_Rewrite ) ) {
			return;
		}

		try {
			$endpoints = new self();
			$payload   = 'oauth-protected-resource' === $matches[1]
				? $endpoints->protected_resource_metadata()
				: $endpoints->authorization_server_metadata();
			$endpoints->respond( $payload );
		} catch ( \Throwable $e ) {
			// Never let the early hook take down WordPress — the rest of
			// the plugin (and its abilities) MUST still register on this
			// request. If we can't serve the metadata here, the normal
			// parse_request handler will pick it up downstream.
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( '[mcp-for-wordpress] well-known early hook bailed: ' . $e->getMessage() );
			}
			return;
		}
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
	}

	public function maybe_flush_rewrites(): void {
		$flushed = (string) get_option( 'tropk_mcp_rewrites_flushed', '' );
		if ( TROPK_MCP_VERSION === $flushed ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( 'tropk_mcp_rewrites_flushed', TROPK_MCP_VERSION, false );
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
		$prm_args = [
			'methods'             => 'GET',
			'callback'            => function (): \WP_REST_Response {
				$response = new \WP_REST_Response( $this->protected_resource_metadata(), 200 );
				$response->header( 'Cache-Control', 'public, max-age=600' );
				return $response;
			},
			'permission_callback' => '__return_true',
		];
		$as_args = [
			'methods'             => 'GET',
			'callback'            => function (): \WP_REST_Response {
				$response = new \WP_REST_Response( $this->authorization_server_metadata(), 200 );
				$response->header( 'Cache-Control', 'public, max-age=600' );
				return $response;
			},
			'permission_callback' => '__return_true',
		];

		register_rest_route( 'tropk-mcp/v1', self::REST_PRM_PATH, $prm_args );
		register_rest_route( 'tropk-mcp/v1', self::REST_AS_PATH, $as_args );

		// RFC 8414 path-aware discovery: clients that take our `issuer` and
		// construct `<issuer>/.well-known/oauth-authorization-server` will
		// land at `/wp-json/tropk-mcp/v1/.well-known/oauth-authorization-server`
		// on hosts where `<host>/.well-known/oauth-authorization-server` is
		// reserved for ACME challenges and 404s. Same handler, same payload —
		// just a URL that lives inside /wp-json/ so the nginx /.well-known/
		// rule doesn't intercept it. Mirror the OpenID Connect Discovery URL
		// too for clients that probe both (ChatGPT, some Anthropic SDKs).
		register_rest_route( 'tropk-mcp/v1', '/.well-known/oauth-authorization-server', $as_args );
		register_rest_route( 'tropk-mcp/v1', '/.well-known/openid-configuration', $as_args );
		register_rest_route( 'tropk-mcp/v1', '/.well-known/oauth-protected-resource', $prm_args );

		// Empty JWKS endpoint. We don't sign JWTs (id_token_signing_alg =
		// "none") but RFC 8414 requires jwks_uri to be a valid URL, and
		// strict clients like Cursor validate its presence with Zod/JSON
		// Schema and fail with "expected string, received undefined" if it's
		// missing. Returning {"keys":[]} is the standard "I have no signing
		// keys" response per RFC 7517 §5.
		$jwks_args = [
			'methods'             => 'GET',
			'callback'            => function (): \WP_REST_Response {
				$response = new \WP_REST_Response( [ 'keys' => [] ], 200 );
				$response->header( 'Cache-Control', 'public, max-age=86400' );
				return $response;
			},
			'permission_callback' => '__return_true',
		];
		register_rest_route( 'tropk-mcp/v1', '/oauth/jwks', $jwks_args );
		register_rest_route( 'tropk-mcp/v1', '/jwks', $jwks_args );
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
	/**
	 * The authorization server's base URL. Set to the plugin's REST
	 * namespace (NOT the WordPress site root) so MCP-SDK-based clients
	 * (mcp-remote, Claude Code, Claude Desktop) that fall back to
	 * constructing default endpoints `<base>/register`, `<base>/token`,
	 * `<base>/authorize`, `<base>/jwks` land inside /wp-json/ where the
	 * plugin actually owns the URL space. Hosts that 404 /.well-known/
	 * at the nginx layer (a common shared-host configuration) used to
	 * make those fallbacks resolve to `<site>/register` and similar
	 * URLs that don't exist, returning Apache 403 and breaking OAuth.
	 */
	public static function as_issuer(): string {
		return rest_url( 'tropk-mcp/v1/' );
	}

	public function authorization_server_metadata(): array {
		$base = self::as_issuer();
		return [
			'issuer'                                => $base,
			'authorization_endpoint'                => AuthorizationEndpoint::url(),
			'token_endpoint'                        => rest_url( 'tropk-mcp/v1/oauth/token' ),
			'registration_endpoint'                 => rest_url( 'tropk-mcp/v1/oauth/register' ),
			'revocation_endpoint'                   => rest_url( 'tropk-mcp/v1/oauth/revoke' ),
			'jwks_uri'                              => rest_url( 'tropk-mcp/v1/oauth/jwks' ),
			'response_types_supported'              => [ 'code' ],
			'response_modes_supported'              => [ 'query' ],
			'grant_types_supported'                 => [ 'authorization_code', 'refresh_token' ],
			'code_challenge_methods_supported'      => [ 'S256' ],
			'token_endpoint_auth_methods_supported' => [ 'none', 'client_secret_basic', 'client_secret_post' ],
			'scopes_supported'                      => Scopes::ALL,
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
			'authorization_servers'     => [ self::as_issuer() ],
			'authorization_server_metadata_endpoint' => self::rest_as_url(),
			'scopes_supported'          => Scopes::ALL,
			'bearer_methods_supported'  => [ 'header' ],
			'resource_documentation'    => 'https://github.com/tropk-ai/mcp-for-wordpress',
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
