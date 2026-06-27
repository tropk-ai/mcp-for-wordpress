<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth;

use Tropk\Mcp\OAuth\Endpoints\MetadataEndpoints;

/**
 * Converts a Bearer access token in the Authorization header into a
 * resolved WordPress user_id by hooking into determine_current_user.
 * On every authenticated MCP request the token's audience (resource)
 * must match the canonical MCP endpoint of this site (RFC 8707) and
 * its scope must be sufficient for the called ability.
 *
 * On invalid/expired/insufficient tokens, returns a 401/403 with a
 * WWW-Authenticate header pointing at the Protected Resource Metadata
 * URL (RFC 9728). The PRM URL is the REST-API version so it is
 * reachable even on hosts that 404 /.well-known/ before WordPress.
 */
final class BearerAuthenticator {

	private const ATTR_TOKEN = 'tropk_mcp_oauth_token_row';

	private Tokens $tokens;

	public function __construct( ?Tokens $tokens = null ) {
		$this->tokens = $tokens ?? new Tokens();
	}

	public function register(): void {
		add_filter( 'determine_current_user', [ $this, 'determine_user' ], 30 );
		add_filter( 'rest_authentication_errors', [ $this, 'enforce_scope_and_audience' ], 30 );
		add_filter( 'rest_post_dispatch', [ $this, 'add_www_authenticate_on_unauthorized' ], 10, 3 );
		// Force `no-store` on every response under our MCP namespace. Without
		// this, URL-keyed edge caches (Cloudflare, Hostinger HCDN, host
		// fastcgi cache, intermediary proxies) cache the 401-with-WWW-
		// Authenticate response and serve it back as a stale generic 401 even
		// after the user provides a valid bearer — OR cache an authenticated
		// 200 reply and serve it back to unauthenticated requests. Either way
		// breaks OAuth discovery for ChatGPT and Claude.ai's web connector.
		// This is the specific class of bug Royal MCP 1.4.15 documented.
		add_filter( 'rest_post_dispatch', [ $this, 'force_no_store_on_mcp_namespace' ], 5, 3 );
	}

	/**
	 * @param int|false|mixed $user_id
	 * @return int|false|mixed
	 */
	public function determine_user( $user_id ) {
		if ( ! empty( $user_id ) ) {
			return $user_id;
		}
		$token = $this->extract_bearer();
		if ( null === $token ) {
			return $user_id;
		}

		$row = $this->tokens->find_by_access_token( $token );
		if ( null === $row || ! $this->tokens->is_valid_for_use( $row ) ) {
			return $user_id;
		}

		$GLOBALS[ self::ATTR_TOKEN ] = $row;
		$this->tokens->mark_used( (int) $row['id'] );

		return (int) $row['user_id'];
	}

	/**
	 * @param \WP_Error|null|true $result
	 * @return \WP_Error|null|true
	 */
	public function enforce_scope_and_audience( $result ) {
		if ( $result instanceof \WP_Error ) {
			return $result;
		}
		$row = $GLOBALS[ self::ATTR_TOKEN ] ?? null;
		if ( ! is_array( $row ) ) {
			return $result;
		}

		$expected_audience = rest_url( 'tropk-mcp/v1/mcp' );
		$audience          = (string) ( $row['resource'] ?? '' );
		if ( '' !== $audience && ! $this->audience_matches( $audience, $expected_audience ) ) {
			return new \WP_Error(
				'tropk_mcp_invalid_audience',
				__( 'Token audience does not match this resource.', 'mcp-for-wordpress' ),
				[ 'status' => 403 ]
			);
		}

		$route = $_SERVER['REQUEST_URI'] ?? '';
		if ( is_string( $route ) && preg_match( '#/wp-json/(tropk-mcp|mcp)/#', $route ) ) {
			$granted = Scopes::parse( (string) ( $row['scope'] ?? '' ) );
			if ( ! Scopes::contains( $granted, [ Scopes::READ ] ) ) {
				return new \WP_Error(
					'tropk_mcp_insufficient_scope',
					__( 'Token is missing the mcp:read scope.', 'mcp-for-wordpress' ),
					[ 'status' => 403 ]
				);
			}
		}

		return $result;
	}

	/**
	 * @param \WP_REST_Response|\WP_HTTP_Response $response
	 */
	public function add_www_authenticate_on_unauthorized( $response, $server, $request ) {
		if ( ! method_exists( $response, 'get_status' ) ) {
			return $response;
		}
		$status = (int) $response->get_status();
		if ( 401 !== $status && 403 !== $status ) {
			return $response;
		}
		$route = method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		if ( '' === $route || ! preg_match( '#^/(tropk-mcp|mcp)/#', $route ) ) {
			return $response;
		}

		// Use the REST URL (`/wp-json/tropk-mcp/v1/oauth-protected-resource`)
		// in the WWW-Authenticate header, NOT the /.well-known URL.
		//
		// Per RFC 9728 §5.1, the resource_metadata value is just "a URL
		// where the protected resource metadata can be retrieved" — any URL
		// works. RFC 9728-aware clients (Claude.ai, Cursor, Lovable,
		// Windsurf) follow the link verbatim, so pointing at /wp-json/
		// works on every host. The /.well-known/ form fails on shared hosts
		// that reserve /.well-known/ for ACME SSL renewals at the nginx
		// layer (Locaweb, UOL, KingHost, RedeHost, some Hostinger configs)
		// — they return a static 404 HTML page before WordPress sees the
		// request, which makes Claude show "Não foi possível registrar"
		// because PRM discovery never reaches our PHP handler.
		//
		// The /wp-json/ URL is always reachable when WordPress's REST API
		// works — and the plugin can't function at all without REST API,
		// so this is the safest invariant we have.
		//
		// ChatGPT (currently disabled in the wizard) probes /.well-known/
		// directly and ignores the WWW-Authenticate hint, so this change
		// doesn't affect it either way.
		$metadata_url = MetadataEndpoints::rest_prm_url();
		$response->header(
			'WWW-Authenticate',
			sprintf(
				'Bearer realm="tropk-core", resource_metadata="%s"',
				$metadata_url
			)
		);
		return $response;
	}

	/**
	 * Stamp `Cache-Control: no-store` on every response under /wp-json/(tropk-mcp|mcp)/
	 * so edge caches don't pin an auth-error response or accidentally serve an
	 * authenticated body back to the next anonymous request. See Royal MCP
	 * 1.4.15 changelog for the full discovery story.
	 *
	 * @param \WP_REST_Response|\WP_HTTP_Response $response
	 */
	public function force_no_store_on_mcp_namespace( $response, $server, $request ) {
		if ( ! method_exists( $request, 'get_route' ) ) {
			return $response;
		}
		$route = (string) $request->get_route();
		if ( '' === $route || ! preg_match( '#^/(tropk-mcp|mcp)/#', $route ) ) {
			return $response;
		}
		if ( method_exists( $response, 'header' ) ) {
			$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private', true );
			$response->header( 'Pragma', 'no-cache', true );
			$response->header( 'Vary', 'Authorization, Origin, Accept', true );
		}
		return $response;
	}

	private function extract_bearer(): ?string {
		$header = '';
		if ( function_exists( 'getallheaders' ) ) {
			$all = getallheaders();
			if ( is_array( $all ) ) {
				foreach ( $all as $name => $value ) {
					if ( 0 === strcasecmp( (string) $name, 'authorization' ) ) {
						$header = (string) $value;
						break;
					}
				}
			}
		}
		if ( '' === $header ) {
			$header = (string) ( $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '' );
		}
		if ( '' === $header ) {
			return null;
		}
		if ( ! preg_match( '/^Bearer\s+([A-Za-z0-9\-._~+\/]+=*)\s*$/i', $header, $matches ) ) {
			return null;
		}
		return $matches[1];
	}

	private function audience_matches( string $token_audience, string $expected ): bool {
		$normalize = static fn( string $u ): string => rtrim( strtolower( $u ), '/' );
		return $normalize( $token_audience ) === $normalize( $expected );
	}
}
