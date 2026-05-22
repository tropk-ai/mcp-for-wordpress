<?php
declare(strict_types=1);

namespace Tropk\Mcp\Security;

/**
 * CORS handling for the MCP and OAuth REST namespaces.
 *
 * The MCP Streamable HTTP transport uses two custom request headers
 * (Mcp-Session-Id, MCP-Protocol-Version) plus Authorization. WordPress
 * core's rest_handle_options_request advertises a generous default but
 * does NOT include those custom MCP headers, so browser-based clients
 * (Claude.ai, ChatGPT) fail the preflight before any auth — surfacing
 * as "Couldn't reach the MCP server".
 *
 * This handler:
 *   1. Adds Mcp-Session-Id, MCP-Protocol-Version, Last-Event-ID to the
 *      rest_allowed_cors_headers list (CORS preflight allow-list).
 *   2. Exposes Mcp-Session-Id and WWW-Authenticate via the response
 *      Access-Control-Expose-Headers so browsers can read them.
 *   3. Short-circuits OPTIONS preflights on /tropk-mcp/v1/* with
 *      a 204 so the request never has to traverse the rest of the
 *      auth chain.
 */
final class CorsHandler {

	private const EXTRA_REQUEST_HEADERS = [
		'Mcp-Session-Id',
		'MCP-Protocol-Version',
		'mcp-session-id',
		'mcp-protocol-version',
		'Last-Event-ID',
	];

	private const EXPOSED_HEADERS = [ 'Mcp-Session-Id', 'MCP-Protocol-Version', 'WWW-Authenticate' ];

	public function register(): void {
		add_filter( 'rest_allowed_cors_headers', [ $this, 'extend_allowed_headers' ], 10, 2 );
		add_filter( 'rest_pre_serve_request', [ $this, 'short_circuit_options' ], 9, 4 );
		add_filter( 'rest_post_dispatch', [ $this, 'add_expose_headers' ], 9, 3 );
	}

	/**
	 * @param array<int, string> $headers
	 * @return array<int, string>
	 */
	public function extend_allowed_headers( $headers, $request ) {
		if ( ! is_array( $headers ) ) {
			$headers = [];
		}
		return array_values( array_unique( array_merge( $headers, self::EXTRA_REQUEST_HEADERS ) ) );
	}

	/**
	 * @param bool             $served
	 * @param \WP_HTTP_Response $result
	 * @param \WP_REST_Request  $request
	 * @param \WP_REST_Server   $server
	 */
	public function short_circuit_options( $served, $result, $request, $server ) {
		$route = method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		if ( ! $this->is_mcp_route( $route ) ) {
			return $served;
		}
		if ( 'OPTIONS' !== strtoupper( $request->get_method() ) ) {
			return $served;
		}

		$origin = get_http_origin();
		if ( '' === (string) $origin || null === $origin ) {
			$origin = (string) ( $_SERVER['HTTP_ORIGIN'] ?? '' );
		}

		nocache_headers();
		if ( $origin ) {
			header( 'Access-Control-Allow-Origin: ' . esc_url_raw( (string) $origin ) );
			header( 'Vary: Origin', false );
			header( 'Access-Control-Allow-Credentials: true' );
		} else {
			header( 'Access-Control-Allow-Origin: *' );
		}
		header( 'Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS' );
		header( 'Access-Control-Allow-Headers: ' . implode( ', ', array_unique( array_merge(
			[ 'Authorization', 'Content-Type', 'Accept', 'Origin', 'X-Requested-With' ],
			self::EXTRA_REQUEST_HEADERS
		) ) ) );
		header( 'Access-Control-Expose-Headers: ' . implode( ', ', self::EXPOSED_HEADERS ) );
		header( 'Access-Control-Max-Age: 600' );

		status_header( 204 );
		return true;
	}

	/**
	 * @param \WP_REST_Response $response
	 */
	public function add_expose_headers( $response, $server, $request ) {
		if ( ! is_object( $response ) || ! method_exists( $response, 'header' ) ) {
			return $response;
		}
		$route = method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		if ( ! $this->is_mcp_route( $route ) ) {
			return $response;
		}
		$response->header( 'Access-Control-Expose-Headers', implode( ', ', self::EXPOSED_HEADERS ) );
		return $response;
	}

	private function is_mcp_route( string $route ): bool {
		if ( '' === $route ) {
			return false;
		}
		return (bool) preg_match( '#^/(tropk-mcp|mcp)/#', $route );
	}
}
