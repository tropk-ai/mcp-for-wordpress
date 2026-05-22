<?php
declare(strict_types=1);

namespace Tropk\Mcp\Backup;

/**
 * Captures pre-mutation snapshots of posts (and their meta + term
 * relationships) so destructive abilities can advertise a rollback handle.
 * Snapshots live under wp-content/uploads/mcp-backups/{Y/m/d}/ as JSON.
 */
final class SnapshotManager {

	private const SUBDIR = 'mcp-backups';

	public function snapshot_post( int $post_id, string $reason = '' ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			throw new \RuntimeException( sprintf( 'Cannot snapshot post %d: not found.', $post_id ) );
		}

		$payload = [
			'snapshot_version' => 1,
			'taken_at'         => gmdate( 'c' ),
			'reason'           => $reason,
			'site_url'         => home_url(),
			'user_id'          => get_current_user_id(),
			'post'             => $post->to_array(),
			'meta'             => get_post_meta( $post_id ),
			'terms'            => $this->collect_terms( $post ),
		];

		$path = $this->write_payload( $post_id, $payload );

		return [
			'snapshot_id' => $this->snapshot_id_for( $path ),
			'path'        => $path,
			'taken_at'    => $payload['taken_at'],
			'reason'      => $reason,
		];
	}

	public function restore_post( string $snapshot_id ): int {
		$path = $this->path_for_snapshot_id( $snapshot_id );
		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
			throw new \RuntimeException( sprintf( 'Snapshot "%s" not found.', $snapshot_id ) );
		}

		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			throw new \RuntimeException( 'Failed to read snapshot file.' );
		}

		$payload = json_decode( $raw, true );
		if ( ! is_array( $payload ) || ! isset( $payload['post']['ID'] ) ) {
			throw new \RuntimeException( 'Snapshot file malformed.' );
		}

		$post_data = $payload['post'];
		$post_id   = (int) $post_data['ID'];

		$result = wp_update_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( 'wp_update_post failed during restore: ' . $result->get_error_message() );
		}

		if ( isset( $payload['meta'] ) && is_array( $payload['meta'] ) ) {
			foreach ( $payload['meta'] as $key => $values ) {
				delete_post_meta( $post_id, $key );
				if ( ! is_array( $values ) ) {
					continue;
				}
				foreach ( $values as $value ) {
					add_post_meta( $post_id, $key, maybe_unserialize( $value ) );
				}
			}
		}

		if ( isset( $payload['terms'] ) && is_array( $payload['terms'] ) ) {
			foreach ( $payload['terms'] as $taxonomy => $term_ids ) {
				wp_set_object_terms( $post_id, array_map( 'intval', $term_ids ), $taxonomy, false );
			}
		}

		return $post_id;
	}

	public function list_snapshots( int $post_id = 0, int $limit = 20 ): array {
		$base = $this->base_dir();
		if ( ! is_dir( $base ) ) {
			return [];
		}

		$files = [];
		$it    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $info ) {
			if ( ! $info->isFile() ) {
				continue;
			}
			$name = $info->getFilename();
			if ( ! str_ends_with( $name, '.json' ) ) {
				continue;
			}
			if ( $post_id > 0 && ! str_starts_with( $name, sprintf( '%d-', $post_id ) ) ) {
				continue;
			}
			$path           = $info->getPathname();
			$files[ $path ] = $info->getMTime();
		}

		arsort( $files );
		$out = [];
		foreach ( array_slice( $files, 0, $limit, true ) as $path => $mtime ) {
			$out[] = [
				'snapshot_id' => $this->snapshot_id_for( $path ),
				'taken_at'    => gmdate( 'c', $mtime ),
			];
		}
		return $out;
	}

	private function collect_terms( \WP_Post $post ): array {
		$taxonomies = get_object_taxonomies( $post->post_type );
		$out        = [];
		foreach ( $taxonomies as $taxonomy ) {
			$ids = wp_get_object_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );
			if ( is_wp_error( $ids ) ) {
				continue;
			}
			$out[ $taxonomy ] = array_map( 'intval', $ids );
		}
		return $out;
	}

	private function write_payload( int $post_id, array $payload ): string {
		$dir = $this->ensure_dir();
		$ts  = gmdate( 'Ymd-His' );
		$rand = wp_generate_password( 6, false, false );
		$file = sprintf( '%d-%s-%s.json', $post_id, $ts, $rand );
		$path = trailingslashit( $dir ) . $file;

		$json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			throw new \RuntimeException( 'Snapshot encode failed.' );
		}

		$written = file_put_contents( $path, $json, LOCK_EX );
		if ( false === $written ) {
			throw new \RuntimeException( 'Snapshot write failed at ' . $path );
		}

		return $path;
	}

	private function ensure_dir(): string {
		$base = $this->base_dir();
		$day  = trailingslashit( $base ) . gmdate( 'Y/m/d' );
		if ( ! wp_mkdir_p( $day ) ) {
			throw new \RuntimeException( 'Cannot create snapshot directory: ' . $day );
		}
		$htaccess = trailingslashit( $base ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( $htaccess, "Require all denied\n" );
		}
		$index = trailingslashit( $base ) . 'index.html';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, '' );
		}
		return $day;
	}

	public function base_dir(): string {
		$uploads = wp_get_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . self::SUBDIR;
	}

	private function snapshot_id_for( string $path ): string {
		$base = $this->base_dir();
		$rel  = ltrim( str_replace( $base, '', $path ), DIRECTORY_SEPARATOR );
		return rtrim( strtr( base64_encode( $rel ), '+/=', '-_~' ), '~' );
	}

	private function path_for_snapshot_id( string $snapshot_id ): ?string {
		$rel = base64_decode( strtr( $snapshot_id, '-_~', '+/=' ) . str_repeat( '=', ( 4 - ( strlen( $snapshot_id ) % 4 ) ) % 4 ), true );
		if ( ! is_string( $rel ) || '' === $rel ) {
			return null;
		}
		if ( str_contains( $rel, '..' ) ) {
			return null;
		}
		$base = $this->base_dir();
		$path = trailingslashit( $base ) . $rel;
		$real = realpath( $path );
		$real_base = realpath( $base );
		if ( ! $real || ! $real_base || ! str_starts_with( $real, $real_base ) ) {
			return null;
		}
		return $real;
	}
}
