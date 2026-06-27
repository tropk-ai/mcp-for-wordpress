<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth\Endpoints;

use Tropk\Mcp\OAuth\ClientRegistry;

/**
 * RFC 7591 Dynamic Client Registration. Anonymous registrations are
 * allowed by default so MCP clients can self-register without an
 * out-of-band setup step, but the gate is filterable via
 * tropk_mcp_allow_dcr so site owners can require an admin-issued
 * registration token when needed.
 */
final class RegistrationEndpoint {

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	public function register_route(): void {
		$args = [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle' ],
			'permission_callback' => [ $this, 'allow' ],
			'args'                => [],
		];

		// Canonical RFC 7591 route.
		register_rest_route( 'tropk-mcp/v1', '/oauth/register', $args );
		// Alias for the OAuth-spec convention where clients construct the
		// registration endpoint as `<authorization_server>/register`. The
		// MCP TypeScript SDK (used by mcp-remote, Claude Desktop's MCP
		// connector, Claude Code, and the @modelcontextprotocol/sdk family)
		// falls back to this construction when /.well-known/oauth-
		// authorization-server cannot be reached — which happens on hosts
		// that reserve /.well-known/ for ACME SSL renewals (SiteGround,
		// some Hostinger configurations). Both routes hit the same handler.
		register_rest_route( 'tropk-mcp/v1', '/register', $args );
	}

	public function allow(): bool {
		return (bool) apply_filters( 'tropk_mcp_allow_dcr', true );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( \WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		try {
			$record = ( new ClientRegistry() )->register(
				[
					'client_name'   => (string) ( $params['client_name'] ?? 'Unnamed MCP client' ),
					'redirect_uris' => (array) ( $params['redirect_uris'] ?? [] ),
					'client_type'   => (string) ( $params['token_endpoint_auth_method'] ?? 'none' ) !== 'none' ? 'confidential' : 'public',
					'scope'         => (string) ( $params['scope'] ?? 'mcp:read' ),
					'grant_types'   => (array) ( $params['grant_types'] ?? [ 'authorization_code', 'refresh_token' ] ),
					'metadata'      => $params,
				]
			);
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'invalid_client_metadata', $e->getMessage(), [ 'status' => 400 ] );
		}

		$response = [
			'client_id'                  => $record['client_id'],
			'client_id_issued_at'        => strtotime( (string) $record['created_at'] . ' UTC' ),
			'client_name'                => $record['client_name'],
			'redirect_uris'              => $record['redirect_uris'],
			'grant_types'                => $record['grant_types'],
			'scope'                      => $record['scope'],
			'token_endpoint_auth_method' => 'confidential' === $record['client_type'] ? 'client_secret_basic' : 'none',
		];
		if ( isset( $record['client_secret'] ) ) {
			$response['client_secret']            = $record['client_secret'];
			$response['client_secret_expires_at'] = 0;
		}

		return new \WP_REST_Response( $response, 201 );
	}
}
