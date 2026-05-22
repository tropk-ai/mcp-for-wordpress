<?php
declare(strict_types=1);

namespace Tropk\Mcp\Ilj;

/**
 * Adapter for Internal Link Juicer. The plugin exposes no public REST
 * API, so this client uses the two stable surfaces that have been
 * confirmed in the plugin's own changelog:
 *
 *   - postmeta `ilj_linkdefinition`: array of keywords; WordPress
 *     serializes it automatically on save.
 *   - custom table `{prefix}ilj_linkindex`: read-only. Column layout is
 *     not part of any documented contract, so this client introspects
 *     it via INFORMATION_SCHEMA before querying.
 *
 * Mutations trigger a reindex by calling wp_update_post(), which is the
 * trigger ILJ itself documents in its support threads.
 */
final class IljClient {

	public const META_KEY = 'ilj_linkdefinition';

	public static function is_active(): bool {
		if ( defined( 'ILJ_VERSION' ) ) {
			return true;
		}
		if ( function_exists( 'is_plugin_active' ) ) {
			return is_plugin_active( 'internal-links/internal-links.php' );
		}
		$active = (array) get_option( 'active_plugins', [] );
		foreach ( $active as $slug ) {
			if ( is_string( $slug ) && str_starts_with( $slug, 'internal-links/' ) ) {
				return true;
			}
		}
		return false;
	}

	public function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ilj_linkindex';
	}

	public function table_exists(): bool {
		global $wpdb;
		$table = $this->table_name();
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return is_string( $found ) && $found === $table;
	}

	/**
	 * @return array<int, string>
	 */
	public function table_columns(): array {
		global $wpdb;
		if ( ! $this->table_exists() ) {
			return [];
		}
		$rows = $wpdb->get_results( 'DESCRIBE `' . esc_sql( $this->table_name() ) . '`', ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}
		return array_values( array_map( static fn( $r ) => (string) ( $r['Field'] ?? '' ), $rows ) );
	}

	public function row_count(): int {
		global $wpdb;
		if ( ! $this->table_exists() ) {
			return 0;
		}
		$count = $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $this->table_name() ) . '`' );
		return is_numeric( $count ) ? (int) $count : 0;
	}

	/**
	 * @return array<int, string>
	 */
	public function get_keywords( int $post_id ): array {
		$value = get_post_meta( $post_id, self::META_KEY, true );
		if ( '' === $value || null === $value ) {
			return [];
		}
		if ( ! is_array( $value ) ) {
			return [];
		}
		$out = [];
		foreach ( $value as $kw ) {
			if ( is_string( $kw ) && '' !== trim( $kw ) ) {
				$out[] = trim( $kw );
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param array<int, string> $keywords
	 */
	public function set_keywords( int $post_id, array $keywords ): array {
		$clean = [];
		foreach ( $keywords as $kw ) {
			if ( ! is_string( $kw ) ) {
				continue;
			}
			$trimmed = trim( $kw );
			if ( '' === $trimmed ) {
				continue;
			}
			$clean[] = $trimmed;
		}
		$clean = array_values( array_unique( $clean ) );

		if ( [] === $clean ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, $clean );
		}

		$this->trigger_reindex( $post_id );
		return $clean;
	}

	/**
	 * Calls wp_update_post() so ILJ's save_post / post_updated handlers
	 * re-run; this is the workaround documented in ILJ support threads
	 * for keyword changes made outside of the post-editing UI.
	 */
	private function trigger_reindex( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		wp_update_post( [
			'ID'               => $post_id,
			'post_modified'    => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', true ),
		] );
	}

	/**
	 * Best-effort lookup of linkindex rows that reference a given post.
	 * Because the column layout is not part of a documented contract, we
	 * scan introspected columns and filter on any *_id column that
	 * matches the post id. Returns the raw rows so the caller can
	 * interpret them.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function rows_referencing_post( int $post_id, int $limit = 50 ): array {
		global $wpdb;
		if ( ! $this->table_exists() ) {
			return [];
		}
		$columns = $this->table_columns();
		$id_cols = array_values( array_filter( $columns, static fn( $c ) => str_ends_with( strtolower( $c ), '_id' ) || 'id' === strtolower( $c ) ) );
		if ( [] === $id_cols ) {
			return [];
		}

		$where_parts = [];
		foreach ( $id_cols as $col ) {
			$where_parts[] = '`' . esc_sql( $col ) . '` = %d';
		}

		$sql = sprintf(
			'SELECT * FROM `%s` WHERE %s LIMIT %%d',
			esc_sql( $this->table_name() ),
			implode( ' OR ', $where_parts )
		);

		$args = array_fill( 0, count( $id_cols ), $post_id );
		$args[] = max( 1, min( 500, $limit ) );

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Returns post IDs that have publish status but appear nowhere in
	 * the linkindex. Honest about its limits when the schema isn't
	 * introspectable.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function orphan_posts( string $post_type = 'post', int $limit = 50 ): array {
		global $wpdb;
		if ( ! $this->table_exists() ) {
			return [];
		}

		$columns = $this->table_columns();
		$id_cols = array_values( array_filter( $columns, static fn( $c ) => str_ends_with( strtolower( $c ), '_id' ) || 'id' === strtolower( $c ) ) );
		if ( [] === $id_cols ) {
			return [];
		}

		$unions = [];
		foreach ( $id_cols as $col ) {
			$unions[] = 'SELECT `' . esc_sql( $col ) . '` AS post_id FROM `' . esc_sql( $this->table_name() ) . '`';
		}
		$ref_sql = implode( ' UNION ', $unions );

		$sql = $wpdb->prepare(
			"SELECT ID, post_title FROM {$wpdb->posts}
			 WHERE post_status = 'publish' AND post_type = %s
			 AND ID NOT IN ( {$ref_sql} )
			 ORDER BY post_date DESC
			 LIMIT %d",
			$post_type,
			max( 1, min( 500, $limit ) )
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}
		return array_map(
			static fn( $r ) => [ 'post_id' => (int) $r['ID'], 'title' => (string) $r['post_title'] ],
			$rows
		);
	}
}
