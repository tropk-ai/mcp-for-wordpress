<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth;

use Tropk\Mcp\OAuth\Endpoints\MetadataEndpoints;

/**
 * ChatGPT's MCP custom-connector enforces three things beyond the baseline
 * MCP authorization spec, and silently fails with "MCP server does not
 * implement OAuth" when any of them is missing. This class adds them:
 *
 *  1. Per-tool `securitySchemes` declaration in the tools/list response.
 *     Per SEP-1488 and the OpenAI Apps SDK auth docs, tools must declare
 *     whether they need OAuth and which scopes. Without this, ChatGPT
 *     decides the server is "unauthenticated" and refuses to start the
 *     OAuth flow.
 *
 *  2. Runtime errors must carry `_meta["mcp/www_authenticate"]` inside
 *     the JSON-RPC error body. The HTTP WWW-Authenticate header alone
 *     is not enough — ChatGPT specifically looks for the OAuth challenge
 *     in the JSON body too.
 *
 *  3. The 401 response body must be valid JSON-RPC 2.0, not the default
 *     WordPress REST error shape (which has a string "code" field and
 *     no "jsonrpc" field). WordPress's default 401 confuses ChatGPT's
 *     JSON-RPC parser before it even gets to read the WWW-Authenticate
 *     header.
 *
 * All of this runs ONLY on responses under /wp-json/(tropk-mcp|mcp)/* so
 * it can't break anything else on the site.
 */
final class ChatGptCompat {

	public function register(): void {
		// Run after BearerAuthenticator (priority 10) so we see the
		// WWW-Authenticate header it set, and rewrite the body.
		add_filter( 'rest_post_dispatch', [ $this, 'rewrite_unauth_as_jsonrpc' ], 20, 3 );
		add_filter( 'rest_post_dispatch', [ $this, 'inject_security_schemes' ], 25, 3 );
	}

	/**
	 * On 401/403 for the MCP route, replace the WordPress REST error shape
	 * with a JSON-RPC 2.0 error response carrying the WWW-Authenticate
	 * challenge under `_meta["mcp/www_authenticate"]`.
	 *
	 * @param \WP_REST_Response|\WP_HTTP_Response $response
	 * @param mixed                              $server   Unused.
	 * @param \WP_REST_Request                   $request
	 * @return \WP_REST_Response|\WP_HTTP_Response
	 */
	public function rewrite_unauth_as_jsonrpc( $response, $server, $request ) {
		if ( ! $this->is_mcp_route( $request ) ) {
			return $response;
		}
		if ( ! method_exists( $response, 'get_status' ) ) {
			return $response;
		}
		$status = (int) $response->get_status();
		if ( 401 !== $status && 403 !== $status ) {
			return $response;
		}

		// Force the status to 401 — ChatGPT (and Claude.ai web) starts OAuth
		// discovery on 401 specifically, not 403. WordPress sometimes returns
		// 403 with "rest_forbidden" when a permission_callback rejects.
		if ( method_exists( $response, 'set_status' ) ) {
			$response->set_status( 401 );
		}

		$resource_metadata = MetadataEndpoints::well_known_prm_url();
		$challenge         = sprintf( 'Bearer resource_metadata="%s"', $resource_metadata );

		// Try to reuse the JSON-RPC `id` from the request body so the response
		// matches the request per JSON-RPC 2.0 §5. If we can't parse it, use
		// null — clients accept null for parse errors / no-id cases.
		$rpc_id = $this->extract_rpc_id( $request );

		$body = [
			'jsonrpc' => '2.0',
			'id'      => $rpc_id,
			'error'   => [
				'code'    => -32001, // "Invalid Request — auth required" per MCP convention.
				'message' => 'Authentication required',
				'data'    => [
					'_meta' => [
						'mcp/www_authenticate' => $challenge,
					],
					'authorization_required' => true,
					'resource_metadata'      => $resource_metadata,
				],
			],
		];

		$response->set_data( $body );
		// Belt-and-braces: also stamp the header in case some earlier filter
		// dropped it (e.g. WP REST authentication errors path bypasses the
		// rest_post_dispatch filter that BearerAuthenticator hooks).
		$response->header( 'WWW-Authenticate', $challenge, true );
		$response->header( 'Content-Type', 'application/json; charset=utf-8', true );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private', true );
		return $response;
	}

	/**
	 * Walks the `tools/list` result and adds a `securitySchemes` entry to
	 * every tool that doesn't already have one. The scope is derived from
	 * the tool's `annotations`:
	 *   - readOnlyHint true  → mcp:read
	 *   - destructiveHint true → mcp:destructive
	 *   - everything else → mcp:write
	 *
	 * Without this, ChatGPT decides the server is unauthenticated and
	 * silently refuses to surface the OAuth UI.
	 *
	 * @param \WP_REST_Response|\WP_HTTP_Response $response
	 * @param mixed                              $server   Unused.
	 * @param \WP_REST_Request                   $request
	 * @return \WP_REST_Response|\WP_HTTP_Response
	 */
	public function inject_security_schemes( $response, $server, $request ) {
		if ( ! $this->is_mcp_route( $request ) ) {
			return $response;
		}
		if ( ! method_exists( $response, 'get_data' ) ) {
			return $response;
		}
		$body = $response->get_data();
		if ( ! is_array( $body ) || ! isset( $body['result']['tools'] ) || ! is_array( $body['result']['tools'] ) ) {
			return $response;
		}

		$tools = $body['result']['tools'];
		foreach ( $tools as $i => $tool ) {
			if ( ! is_array( $tool ) ) {
				continue;
			}
			if ( isset( $tool['securitySchemes'] ) && is_array( $tool['securitySchemes'] ) && [] !== $tool['securitySchemes'] ) {
				continue;
			}
			$annotations = is_array( $tool['annotations'] ?? null ) ? $tool['annotations'] : [];
			$readonly    = (bool) ( $annotations['readOnlyHint'] ?? false );
			$destructive = (bool) ( $annotations['destructiveHint'] ?? false );

			if ( $destructive ) {
				$scope = 'mcp:destructive';
			} elseif ( $readonly ) {
				$scope = 'mcp:read';
			} else {
				$scope = 'mcp:write';
			}

			$tools[ $i ]['securitySchemes'] = [
				[ 'type' => 'oauth2', 'scopes' => [ $scope ] ],
			];
		}
		$body['result']['tools'] = $tools;
		$response->set_data( $body );
		return $response;
	}

	/**
	 * Only the JSON-RPC `/mcp` endpoint itself should get the JSON-RPC error
	 * envelope + securitySchemes rewrite. The OAuth subroutes (/oauth/token,
	 * /oauth/register, /oauth/authorize, /oauth/revoke, /oauth-protected-
	 * resource, /oauth-authorization-server) return RFC 6749 / RFC 7591 /
	 * RFC 9728 errors and metadata respectively — rewriting those into
	 * JSON-RPC breaks Claude.ai's OAuth flow (it cannot parse the 401 our
	 * DCR endpoint returns for invalid client_id, etc.).
	 *
	 * The `force_no_store_on_mcp_namespace` filter elsewhere still covers
	 * the entire namespace, because edge-cache poisoning is bad everywhere.
	 */
	private function is_mcp_route( $request ): bool {
		if ( ! method_exists( $request, 'get_route' ) ) {
			return false;
		}
		$route = (string) $request->get_route();
		if ( '' === $route ) {
			return false;
		}
		// Allow only the JSON-RPC endpoint itself, not /oauth/* etc.
		return (bool) preg_match( '#^/(tropk-mcp|mcp)/v[0-9]+/mcp(/.*)?$#', $route );
	}

	private function extract_rpc_id( \WP_REST_Request $request ) {
		// Body may be already parsed or still a raw string depending on the
		// route's content-type handling. Tolerate both.
		$body = $request->get_body();
		if ( ! is_string( $body ) || '' === $body ) {
			return null;
		}
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}
		if ( isset( $decoded['id'] ) ) {
			return $decoded['id'];
		}
		// Batch request — pick the first id we find.
		if ( isset( $decoded[0]['id'] ) ) {
			return $decoded[0]['id'];
		}
		return null;
	}
}
