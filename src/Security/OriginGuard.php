<?php
declare(strict_types=1);

namespace Tropk\Mcp\Security;

/**
 * Enforces an allowlist on the Origin header for MCP REST requests.
 * Mitigates the CSRF + DNS-rebinding class behind CVE-2025-49596 by
 * refusing cross-origin browser-driven calls that the user did not
 * explicitly enable.
 *
 * Allowlist can be extended via the tropk_mcp_allowed_origins filter.
 */
final class OriginGuard {

	public function register(): void {
		add_filter( 'rest_pre_dispatch', [ $this, 'enforce' ], 5, 3 );
	}

	/**
	 * @param mixed            $result
	 * @param \WP_REST_Server  $server
	 * @param \WP_REST_Request $request
	 * @return mixed
	 */
	public function enforce( $result, $server, $request ) {
		if ( null !== $result ) {
			return $result;
		}

		$route = is_object( $request ) && method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		if ( '' === $route || ! $this->is_mcp_route( $route ) ) {
			return $result;
		}

		$method = is_object( $request ) && method_exists( $request, 'get_method' ) ? strtoupper( (string) $request->get_method() ) : 'GET';
		if ( 'OPTIONS' === $method ) {
			return $result;
		}

		$origin = is_object( $request ) && method_exists( $request, 'get_header' ) ? (string) $request->get_header( 'origin' ) : '';

		if ( '' === $origin ) {
			return $result;
		}

		if ( $this->is_allowed( $origin ) ) {
			return $result;
		}

		return new \WP_Error(
			'tropk_mcp_forbidden_origin',
			__( 'Origin not allowed for MCP requests.', 'mcp-for-wordpress' ),
			[ 'status' => 403 ]
		);
	}

	private function is_mcp_route( string $route ): bool {
		return (bool) preg_match( '#^/(tropk-mcp|mcp)/#', $route );
	}

	public function is_allowed( string $origin ): bool {
		$normalized = $this->normalize_origin( $origin );
		if ( '' === $normalized ) {
			return false;
		}

		$allowed = $this->allowed_origins();
		foreach ( $allowed as $candidate ) {
			$normalized_candidate = $this->normalize_origin( $candidate );
			if ( '' === $normalized_candidate ) {
				continue;
			}
			if ( strcasecmp( $normalized_candidate, $normalized ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array<int, string>
	 */
	public function allowed_origins(): array {
		$defaults = [
			home_url(),
			site_url(),
			admin_url(),
			'https://claude.ai',
			'https://chatgpt.com',
			'https://chat.openai.com',
			'https://cursor.sh',
			'https://app.windsurf.com',
		];

		$option = get_option( 'tropk_mcp_allowed_origins', [] );
		if ( is_string( $option ) && '' !== $option ) {
			$option = array_filter( array_map( 'trim', explode( "\n", $option ) ) );
		}
		if ( ! is_array( $option ) ) {
			$option = [];
		}

		$origins = array_merge( $defaults, $option );
		$origins = apply_filters( 'tropk_mcp_allowed_origins', $origins );

		return is_array( $origins ) ? array_values( array_unique( array_filter( array_map( 'strval', $origins ) ) ) ) : [];
	}

	private function normalize_origin( string $origin ): string {
		$parts = wp_parse_url( $origin );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
		$host   = strtolower( (string) $parts['host'] );
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		return $scheme . '://' . $host . $port;
	}
}
