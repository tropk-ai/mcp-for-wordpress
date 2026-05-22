<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth;

/**
 * Single-use authorization codes with a PKCE S256 challenge attached.
 * Codes expire in 10 minutes. Successful redemption marks the row used
 * atomically; a second redemption attempt is rejected and all tokens
 * issued from the same code are invalidated as a precaution.
 */
final class AuthorizationCodes {

	public const TTL_SECONDS = 600;

	/**
	 * @param array<string, mixed> $args
	 * @return string The plaintext authorization code (returned to the client).
	 */
	public function issue( array $args ): string {
		global $wpdb;

		$code = Crypto::new_token( 32 );
		$wpdb->insert(
			Tables::codes(),
			[
				'code_hash'             => Crypto::hash( $code ),
				'client_id'             => (string) $args['client_id'],
				'user_id'               => (int) $args['user_id'],
				'redirect_uri'          => (string) $args['redirect_uri'],
				'code_challenge'        => (string) $args['code_challenge'],
				'code_challenge_method' => 'S256',
				'scope'                 => (string) $args['scope'],
				'resource'              => isset( $args['resource'] ) ? (string) $args['resource'] : null,
				'expires_at'            => gmdate( 'Y-m-d H:i:s', time() + self::TTL_SECONDS ),
				'used'                  => 0,
			],
			[ '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ]
		);
		return $code;
	}

	/**
	 * Atomically marks a code as used and returns its record.
	 *
	 * @return array<string, mixed>|null Null if missing/expired/already used.
	 */
	public function consume( string $code ): ?array {
		global $wpdb;
		$hash = Crypto::hash( $code );

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Tables::codes() . ' WHERE code_hash = %s', $hash ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		if ( (int) $row['used'] !== 0 ) {
			return null;
		}
		if ( strtotime( (string) $row['expires_at'] . ' UTC' ) <= time() ) {
			return null;
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Tables::codes() . ' SET used = 1 WHERE code_hash = %s AND used = 0',
				$hash
			)
		);
		if ( false === $updated || 0 === $updated ) {
			return null;
		}
		return $row;
	}

	public function purge_expired(): int {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s' );
		$count  = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Tables::codes() . ' WHERE expires_at < %s OR used = 1', $cutoff ) );
		return (int) ( $count ?: 0 );
	}
}
