<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth\Endpoints;

use Tropk\Mcp\OAuth\Tokens;

/**
 * RFC 7009 token revocation. Accepts access_token or refresh_token
 * hints; per spec, the server returns 200 even when the token is not
 * found, so clients cannot probe.
 */
final class RevocationEndpoint {

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	public function register_route(): void {
		register_rest_route(
			'tropk-mcp/v1',
			'/oauth/revoke',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_body_params();
		$token = (string) ( $params['token'] ?? '' );
		$hint  = (string) ( $params['token_type_hint'] ?? '' );

		if ( '' !== $token ) {
			$store = new Tokens();
			if ( 'refresh_token' === $hint ) {
				$store->revoke_by_refresh( $token );
			} elseif ( 'access_token' === $hint ) {
				$store->revoke_by_access( $token );
			} else {
				$store->revoke_by_access( $token ) || $store->revoke_by_refresh( $token );
			}
		}

		$response = new \WP_REST_Response( null, 200 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
