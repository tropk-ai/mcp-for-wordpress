<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth;

/**
 * Paired access + refresh token issuer with rotation lineage. Access
 * tokens default to 1 hour; refresh tokens to 14 days. On refresh, the
 * old token row is revoked and the new one is linked via rotated_from.
 * If a refresh token is presented twice, reuse detection revokes the
 * entire lineage and rejects the request (RFC 9700 §4.13).
 */
final class Tokens {

	public const ACCESS_TTL  = 3600;
	public const REFRESH_TTL = 1209600;

	/**
	 * @param array<string, mixed> $args
	 * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string, token_type: string}
	 */
	public function issue( array $args ): array {
		global $wpdb;

		$access  = Crypto::new_token( 32 );
		$refresh = Crypto::new_token( 32 );

		$now            = time();
		$access_exp     = $now + (int) ( $args['access_ttl'] ?? self::ACCESS_TTL );
		$refresh_exp    = $now + (int) ( $args['refresh_ttl'] ?? self::REFRESH_TTL );

		$wpdb->insert(
			Tables::tokens(),
			[
				'access_token_hash'   => Crypto::hash( $access ),
				'refresh_token_hash'  => Crypto::hash( $refresh ),
				'client_id'           => (string) $args['client_id'],
				'user_id'             => (int) $args['user_id'],
				'scope'               => (string) $args['scope'],
				'resource'            => isset( $args['resource'] ) ? (string) $args['resource'] : null,
				'access_expires_at'   => gmdate( 'Y-m-d H:i:s', $access_exp ),
				'refresh_expires_at'  => gmdate( 'Y-m-d H:i:s', $refresh_exp ),
				'revoked'             => 0,
				'rotated_from'        => isset( $args['rotated_from'] ) ? (string) $args['rotated_from'] : null,
				'created_at'          => gmdate( 'Y-m-d H:i:s', $now ),
			],
			[ '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
		);

		return [
			'access_token'  => $access,
			'refresh_token' => $refresh,
			'expires_in'    => $access_exp - $now,
			'scope'         => (string) $args['scope'],
			'token_type'    => 'Bearer',
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_access_token( string $access_token ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Tables::tokens() . ' WHERE access_token_hash = %s', Crypto::hash( $access_token ) ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_refresh_token( string $refresh_token ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Tables::tokens() . ' WHERE refresh_token_hash = %s', Crypto::hash( $refresh_token ) ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	public function is_valid_for_use( array $row ): bool {
		if ( (int) ( $row['revoked'] ?? 0 ) !== 0 ) {
			return false;
		}
		return strtotime( (string) $row['access_expires_at'] . ' UTC' ) > time();
	}

	public function mark_used( int $token_id ): void {
		global $wpdb;
		$wpdb->update(
			Tables::tokens(),
			[ 'last_used_at' => current_time( 'mysql', true ) ],
			[ 'id' => $token_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	public function revoke_by_refresh( string $refresh_token ): bool {
		global $wpdb;
		$updated = $wpdb->update(
			Tables::tokens(),
			[ 'revoked' => 1 ],
			[ 'refresh_token_hash' => Crypto::hash( $refresh_token ) ],
			[ '%d' ],
			[ '%s' ]
		);
		return false !== $updated && $updated > 0;
	}

	public function revoke_by_access( string $access_token ): bool {
		global $wpdb;
		$updated = $wpdb->update(
			Tables::tokens(),
			[ 'revoked' => 1 ],
			[ 'access_token_hash' => Crypto::hash( $access_token ) ],
			[ '%d' ],
			[ '%s' ]
		);
		return false !== $updated && $updated > 0;
	}

	/**
	 * Walks the rotation lineage that produced $row and revokes every
	 * descendant. Called when reuse of an already-rotated refresh token
	 * is detected.
	 */
	public function revoke_lineage( array $row ): void {
		global $wpdb;
		$table = Tables::tokens();

		// Walk backwards to ancestors.
		$ancestors = [];
		$cursor    = (string) $row['refresh_token_hash'];
		while ( '' !== $cursor && ! in_array( $cursor, $ancestors, true ) ) {
			$ancestors[] = $cursor;
			$parent = $wpdb->get_var( $wpdb->prepare( "SELECT rotated_from FROM {$table} WHERE refresh_token_hash = %s", $cursor ) );
			$cursor = is_string( $parent ) ? $parent : '';
		}

		// Walk forwards to descendants.
		$queue       = [ (string) $row['refresh_token_hash'] ];
		$descendants = [];
		while ( [] !== $queue ) {
			$hash    = array_shift( $queue );
			$descendants[] = $hash;
			$children = $wpdb->get_col( $wpdb->prepare( "SELECT refresh_token_hash FROM {$table} WHERE rotated_from = %s", $hash ) );
			foreach ( (array) $children as $child ) {
				if ( ! in_array( $child, $descendants, true ) && ! in_array( $child, $queue, true ) ) {
					$queue[] = (string) $child;
				}
			}
		}

		$all = array_values( array_unique( array_merge( $ancestors, $descendants ) ) );
		if ( [] === $all ) {
			return;
		}
		$placeholders = implode( ',', array_fill( 0, count( $all ), '%s' ) );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET revoked = 1 WHERE refresh_token_hash IN ({$placeholders})",
				...$all
			)
		);
	}

	public function purge_expired(): int {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s' );
		return (int) ( $wpdb->query(
			$wpdb->prepare( 'DELETE FROM ' . Tables::tokens() . ' WHERE refresh_expires_at < %s', $cutoff )
		) ?: 0 );
	}
}
