<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth\Endpoints;

use Tropk\Mcp\OAuth\AuthorizationCodes;
use Tropk\Mcp\OAuth\ClientRegistry;
use Tropk\Mcp\OAuth\Crypto;
use Tropk\Mcp\OAuth\Tokens;

/**
 * POST /oauth/token. Implements the authorization_code grant (with
 * PKCE S256 verification) and the refresh_token grant (with rotation
 * + reuse detection per RFC 9700 §4.13).
 *
 * Both `client_secret_basic` and `client_secret_post` client
 * authentication methods are accepted for confidential clients; public
 * clients authenticate by client_id + PKCE alone.
 */
final class TokenEndpoint {

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	public function register_route(): void {
		$args = [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle' ],
			'permission_callback' => '__return_true',
		];
		register_rest_route( 'tropk-mcp/v1', '/oauth/token', $args );
		// Alias matching the MCP SDK's default-endpoint fallback when
		// .well-known/oauth-authorization-server is unreachable. See the
		// matching comment in RegistrationEndpoint::register_route().
		register_rest_route( 'tropk-mcp/v1', '/token', $args );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( \WP_REST_Request $request ) {
		$params = $request->get_body_params();
		if ( ! is_array( $params ) || [] === $params ) {
			$params = $request->get_params();
		}

		$grant = (string) ( $params['grant_type'] ?? '' );
		switch ( $grant ) {
			case 'authorization_code':
				return $this->authorization_code( $request, $params );
			case 'refresh_token':
				return $this->refresh_token( $request, $params );
			default:
				return new \WP_Error( 'unsupported_grant_type', 'grant_type must be authorization_code or refresh_token.', [ 'status' => 400 ] );
		}
	}

	/**
	 * @param array<string, mixed> $params
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function authorization_code( \WP_REST_Request $request, array $params ) {
		$client = $this->authenticate_client( $request, $params );
		if ( $client instanceof \WP_Error ) {
			return $client;
		}

		$code         = (string) ( $params['code'] ?? '' );
		$code_verifier = (string) ( $params['code_verifier'] ?? '' );
		$redirect_uri = (string) ( $params['redirect_uri'] ?? '' );
		$resource     = isset( $params['resource'] ) ? (string) $params['resource'] : null;

		if ( '' === $code || '' === $code_verifier || '' === $redirect_uri ) {
			return new \WP_Error( 'invalid_request', 'code, code_verifier and redirect_uri are required.', [ 'status' => 400 ] );
		}

		$row = ( new AuthorizationCodes() )->consume( $code );
		if ( null === $row ) {
			return new \WP_Error( 'invalid_grant', 'Authorization code invalid or already used.', [ 'status' => 400 ] );
		}
		if ( $row['client_id'] !== $client['client_id'] ) {
			return new \WP_Error( 'invalid_grant', 'Authorization code was not issued to this client.', [ 'status' => 400 ] );
		}
		if ( $row['redirect_uri'] !== $redirect_uri ) {
			return new \WP_Error( 'invalid_grant', 'redirect_uri does not match the authorization request.', [ 'status' => 400 ] );
		}
		if ( ! Crypto::verify_pkce( $code_verifier, (string) $row['code_challenge'], (string) ( $row['code_challenge_method'] ?? 'S256' ) ) ) {
			return new \WP_Error( 'invalid_grant', 'PKCE verification failed.', [ 'status' => 400 ] );
		}
		if ( null !== $resource && '' !== $resource && rtrim( strtolower( $resource ), '/' ) !== rtrim( strtolower( (string) ( $row['resource'] ?? '' ) ), '/' ) ) {
			return new \WP_Error( 'invalid_target', 'resource does not match the authorization request.', [ 'status' => 400 ] );
		}

		$tokens = ( new Tokens() )->issue(
			[
				'client_id' => (string) $row['client_id'],
				'user_id'   => (int) $row['user_id'],
				'scope'     => (string) $row['scope'],
				'resource'  => (string) ( $row['resource'] ?? rest_url( 'tropk-mcp/v1/mcp' ) ),
			]
		);

		return $this->ok( $tokens );
	}

	/**
	 * @param array<string, mixed> $params
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function refresh_token( \WP_REST_Request $request, array $params ) {
		$client = $this->authenticate_client( $request, $params );
		if ( $client instanceof \WP_Error ) {
			return $client;
		}
		if ( ! ( new ClientRegistry() )->allows_grant( $client, 'refresh_token' ) ) {
			return new \WP_Error( 'unauthorized_client', 'Client is not authorized for refresh_token.', [ 'status' => 400 ] );
		}

		$refresh = (string) ( $params['refresh_token'] ?? '' );
		if ( '' === $refresh ) {
			return new \WP_Error( 'invalid_request', 'refresh_token is required.', [ 'status' => 400 ] );
		}

		$store = new Tokens();
		$row   = $store->find_by_refresh_token( $refresh );
		if ( null === $row ) {
			return new \WP_Error( 'invalid_grant', 'Unknown refresh token.', [ 'status' => 400 ] );
		}
		if ( $row['client_id'] !== $client['client_id'] ) {
			return new \WP_Error( 'invalid_grant', 'refresh_token was not issued to this client.', [ 'status' => 400 ] );
		}
		if ( (int) ( $row['revoked'] ?? 0 ) !== 0 ) {
			// Reuse detection: revoke the entire lineage.
			$store->revoke_lineage( $row );
			return new \WP_Error( 'invalid_grant', 'refresh_token revoked.', [ 'status' => 400 ] );
		}
		if ( strtotime( (string) $row['refresh_expires_at'] . ' UTC' ) <= time() ) {
			return new \WP_Error( 'invalid_grant', 'refresh_token expired.', [ 'status' => 400 ] );
		}

		// Narrowing the scope on refresh is allowed (OAuth 2.1 §6).
		$scope = isset( $params['scope'] ) ? (string) $params['scope'] : (string) $row['scope'];

		// Rotate.
		$store->revoke_by_refresh( $refresh );
		$tokens = $store->issue(
			[
				'client_id'    => (string) $row['client_id'],
				'user_id'      => (int) $row['user_id'],
				'scope'        => $scope,
				'resource'     => (string) ( $row['resource'] ?? rest_url( 'tropk-mcp/v1/mcp' ) ),
				'rotated_from' => (string) $row['refresh_token_hash'],
			]
		);

		return $this->ok( $tokens );
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>|\WP_Error
	 */
	private function authenticate_client( \WP_REST_Request $request, array $params ) {
		$registry = new ClientRegistry();

		$header = (string) $request->get_header( 'authorization' );
		$client_id     = '';
		$client_secret = '';
		if ( '' !== $header && 0 === stripos( $header, 'Basic ' ) ) {
			$decoded = base64_decode( substr( $header, 6 ), true );
			if ( is_string( $decoded ) && false !== strpos( $decoded, ':' ) ) {
				[ $client_id, $client_secret ] = explode( ':', $decoded, 2 );
			}
		}
		if ( '' === $client_id ) {
			$client_id     = (string) ( $params['client_id'] ?? '' );
			$client_secret = (string) ( $params['client_secret'] ?? '' );
		}
		if ( '' === $client_id ) {
			return new \WP_Error( 'invalid_client', 'client_id is required.', [ 'status' => 401 ] );
		}

		$client = $registry->find( $client_id );
		if ( null === $client ) {
			return new \WP_Error( 'invalid_client', 'Unknown client.', [ 'status' => 401 ] );
		}
		if ( 'confidential' === $client['client_type'] ) {
			if ( ! $registry->verify_secret( $client, $client_secret ) ) {
				return new \WP_Error( 'invalid_client', 'Client authentication failed.', [ 'status' => 401 ] );
			}
		}
		return $client;
	}

	/**
	 * @param array<string, mixed> $body
	 */
	private function ok( array $body ): \WP_REST_Response {
		$response = new \WP_REST_Response( $body, 200 );
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}
}
