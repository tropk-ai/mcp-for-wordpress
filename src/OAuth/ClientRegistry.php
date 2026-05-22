<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth;

final class ClientRegistry {

	/**
	 * Register a new OAuth client. Returns the client record (with
	 * client_secret for confidential clients — show once).
	 *
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public function register( array $args ): array {
		global $wpdb;

		$name = sanitize_text_field( (string) ( $args['client_name'] ?? 'Unnamed MCP client' ) );
		$redirect_uris = (array) ( $args['redirect_uris'] ?? [] );
		$redirect_uris = $this->sanitize_redirect_uris( $redirect_uris );
		if ( [] === $redirect_uris ) {
			throw new \RuntimeException( 'At least one redirect_uri is required.' );
		}

		$client_type = strtolower( (string) ( $args['client_type'] ?? 'public' ) );
		if ( ! in_array( $client_type, [ 'public', 'confidential' ], true ) ) {
			$client_type = 'public';
		}

		$scope = (string) ( $args['scope'] ?? Scopes::serialize( Scopes::DEFAULT_REQUESTED ) );
		$scope = Scopes::serialize( Scopes::parse( $scope ) );
		if ( '' === $scope ) {
			$scope = Scopes::READ;
		}

		$grant_types_raw = (array) ( $args['grant_types'] ?? [ 'authorization_code', 'refresh_token' ] );
		$grants          = $this->sanitize_grant_types( $grant_types_raw );

		$client_id     = Crypto::new_client_id();
		$client_secret = null;
		$secret_hash   = null;
		if ( 'confidential' === $client_type ) {
			$client_secret = Crypto::new_token( 32 );
			$secret_hash   = Crypto::hash( $client_secret );
		}

		$metadata = isset( $args['metadata'] ) && is_array( $args['metadata'] ) ? $args['metadata'] : [];

		$wpdb->insert(
			Tables::clients(),
			[
				'client_id'          => $client_id,
				'client_name'        => $name,
				'client_secret_hash' => $secret_hash,
				'client_type'        => $client_type,
				'redirect_uris'      => wp_json_encode( $redirect_uris ),
				'grant_types'        => implode( ',', $grants ),
				'scope'              => $scope,
				'metadata'           => wp_json_encode( $metadata ),
				'created_at'         => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		$record = $this->find( $client_id );
		if ( null === $record ) {
			throw new \RuntimeException( 'Client registration failed.' );
		}
		if ( null !== $client_secret ) {
			$record['client_secret'] = $client_secret;
		}
		return $record;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find( string $client_id ): ?array {
		global $wpdb;
		if ( '' === $client_id ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Tables::clients() . ' WHERE client_id = %s', $client_id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		$row['redirect_uris'] = (array) ( json_decode( (string) $row['redirect_uris'], true ) ?: [] );
		$row['grant_types']   = array_filter( array_map( 'trim', explode( ',', (string) $row['grant_types'] ) ) );
		$row['metadata']      = (array) ( json_decode( (string) $row['metadata'], true ) ?: [] );
		return $row;
	}

	public function verify_secret( array $client, string $secret ): bool {
		if ( 'confidential' !== ( $client['client_type'] ?? '' ) ) {
			return true;
		}
		$hash = (string) ( $client['client_secret_hash'] ?? '' );
		if ( '' === $hash ) {
			return false;
		}
		return Crypto::safe_equals( $hash, Crypto::hash( $secret ) );
	}

	public function allows_redirect( array $client, string $redirect_uri ): bool {
		$registered = (array) ( $client['redirect_uris'] ?? [] );
		foreach ( $registered as $candidate ) {
			if ( Crypto::safe_equals( (string) $candidate, $redirect_uri ) ) {
				return true;
			}
		}
		return false;
	}

	public function allows_grant( array $client, string $grant_type ): bool {
		$grants = (array) ( $client['grant_types'] ?? [] );
		return in_array( $grant_type, $grants, true );
	}

	public function delete( string $client_id ): bool {
		global $wpdb;
		$deleted = $wpdb->delete( Tables::clients(), [ 'client_id' => $client_id ], [ '%s' ] );
		return false !== $deleted && $deleted > 0;
	}

	/**
	 * @param array<int, mixed> $uris
	 * @return array<int, string>
	 */
	private function sanitize_redirect_uris( array $uris ): array {
		$out = [];
		foreach ( $uris as $uri ) {
			$uri = trim( (string) $uri );
			if ( '' === $uri ) {
				continue;
			}
			$parts = wp_parse_url( $uri );
			if ( ! is_array( $parts ) || empty( $parts['scheme'] ) ) {
				continue;
			}
			$scheme = strtolower( (string) $parts['scheme'] );
			$is_loopback = isset( $parts['host'] ) && in_array( strtolower( (string) $parts['host'] ), [ '127.0.0.1', 'localhost', '::1' ], true );
			if ( 'https' !== $scheme && ! $is_loopback && ! preg_match( '/^[a-z][a-z0-9+.-]*$/', $scheme ) ) {
				continue;
			}
			// Disallow URLs with fragments per OAuth 2.1.
			if ( isset( $parts['fragment'] ) ) {
				continue;
			}
			$out[] = $uri;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param array<int, mixed> $grants
	 * @return array<int, string>
	 */
	private function sanitize_grant_types( array $grants ): array {
		$allowed = [ 'authorization_code', 'refresh_token' ];
		$out     = [];
		foreach ( $grants as $g ) {
			$g = (string) $g;
			if ( in_array( $g, $allowed, true ) ) {
				$out[] = $g;
			}
		}
		if ( [] === $out ) {
			$out = [ 'authorization_code' ];
		}
		return array_values( array_unique( $out ) );
	}
}
