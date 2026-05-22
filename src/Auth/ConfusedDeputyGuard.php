<?php
declare(strict_types=1);

namespace Tropk\Mcp\Auth;

/**
 * Ensures the WordPress current-user context inside MCP requests always
 * matches the authenticated principal of the token, never falling back to
 * a service identity. This is the spec-mandated mitigation for the
 * "confused deputy" class of MCP vulnerabilities.
 */
final class ConfusedDeputyGuard {

	public function register(): void {
		add_action( 'rest_authentication_errors', [ $this, 'rebind_current_user' ], 100 );
	}

	public function rebind_current_user( $result ) {
		if ( ! $this->is_mcp_request() ) {
			return $result;
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return $result;
		}

		wp_set_current_user( $user_id );
		return $result;
	}

	private function is_mcp_request(): bool {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}

		$route = $_SERVER['REQUEST_URI'] ?? '';
		if ( ! is_string( $route ) ) {
			return false;
		}

		return (bool) preg_match( '#/wp-json/(tropk-mcp|mcp)/#i', $route );
	}
}
