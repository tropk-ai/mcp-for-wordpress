<?php
/**
 * Performance abilities for the Abilities API.
 *
 * Registers 7 abilities under the `perf/*` namespace covering DB cleanup,
 * thumbnail regeneration, maintenance mode, rewrite-rules flush, system
 * info and a high-level metrics snapshot. The `perf-extra/` prefix is
 * used for the few that overlap with our existing `tropk-mcp/perf-*`
 * abilities.
 *
 * @package Tropk\Mcp\Extras
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_abilities_api_init',
	static function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'tropk-perf/optimize-db',
			[
				'label'               => 'Performance: optimize MySQL tables',
     'category'            => 'tropk-core',
				'description'         => 'Runs OPTIMIZE TABLE on every InnoDB/MyISAM table owned by this WordPress install.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					global $wpdb;
					$tables = $wpdb->get_col( 'SHOW TABLES' );
					$prefix = $wpdb->prefix;
					$ours   = array_filter( $tables, static fn( $t ) => str_starts_with( (string) $t, $prefix ) );
					$out    = [];
					foreach ( $ours as $t ) {
						$ok    = $wpdb->query( 'OPTIMIZE TABLE `' . esc_sql( (string) $t ) . '`' );
						$out[] = [ 'table' => $t, 'result' => false !== $ok ? 'ok' : 'failed' ];
					}
					return [ 'optimized' => true, 'tables' => $out ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-perf/cleanup-db',
			[
				'label'               => 'Performance: clean up DB',
     'category'            => 'tropk-core',
				'description'         => 'Deletes expired transients, spam comments, trashed comments, post revisions older than a cap, and orphaned postmeta.',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'keep_revisions' => [ 'type' => 'integer', 'default' => 5, 'description' => 'Keep this many recent revisions per post (default 5).' ],
						'expired_transients' => [ 'type' => 'boolean', 'default' => true ],
						'spam_comments'      => [ 'type' => 'boolean', 'default' => true ],
						'trashed_comments'   => [ 'type' => 'boolean', 'default' => true ],
						'orphan_meta'        => [ 'type' => 'boolean', 'default' => true ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					global $wpdb;
					$stats = [];
					if ( ! empty( $input['expired_transients'] ?? true ) ) {
						$stats['expired_transients'] = (int) $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_timeout\\_%' AND option_value < UNIX_TIMESTAMP()" );
					}
					if ( ! empty( $input['spam_comments'] ?? true ) ) {
						$stats['spam_comments'] = (int) $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
					}
					if ( ! empty( $input['trashed_comments'] ?? true ) ) {
						$stats['trashed_comments'] = (int) $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );
					}
					$keep = max( 1, (int) ( $input['keep_revisions'] ?? 5 ) );
					$revs_killed = 0;
					$ids   = $wpdb->get_col( "SELECT post_parent FROM {$wpdb->posts} WHERE post_type = 'revision' GROUP BY post_parent" );
					foreach ( $ids as $parent ) {
						$kept   = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'revision' ORDER BY post_date DESC LIMIT %d", (int) $parent, $keep ) );
						$keep_in = implode( ',', array_map( 'intval', $kept ) );
						$keep_in = '' !== $keep_in ? $keep_in : '0';
						$revs_killed += (int) $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_parent = " . (int) $parent . " AND post_type = 'revision' AND ID NOT IN ($keep_in)" );
					}
					$stats['revisions_deleted'] = $revs_killed;
					if ( ! empty( $input['orphan_meta'] ?? true ) ) {
						$stats['orphan_postmeta'] = (int) $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL" );
					}
					return [ 'cleaned' => true, 'stats' => $stats ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => true, 'idempotent' => false ] ],
			]
		);

		wp_register_ability(
			'tropk-perf/regenerate-thumbnails',
			[
				'label'               => 'Performance: regenerate thumbnails',
     'category'            => 'tropk-core',
				'description'         => 'Regenerate intermediate image sizes for the most recently uploaded N images.',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
						require_once ABSPATH . 'wp-admin/includes/image.php';
					}
					$query     = new \WP_Query(
						[
							'post_type'      => 'attachment',
							'post_status'    => 'inherit',
							'post_mime_type' => 'image',
							'posts_per_page' => (int) ( $input['limit'] ?? 50 ),
						]
					);
					$processed = 0;
					foreach ( $query->posts as $att ) {
						$file = get_attached_file( $att->ID );
						if ( ! $file || ! file_exists( $file ) ) {
							continue;
						}
						$meta = wp_generate_attachment_metadata( $att->ID, $file );
						if ( is_array( $meta ) ) {
							wp_update_attachment_metadata( $att->ID, $meta );
							++$processed;
						}
					}
					return [ 'processed' => $processed, 'requested' => (int) ( $input['limit'] ?? 50 ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'upload_files' ) && current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-perf/maintenance-mode',
			[
				'label'               => 'Performance: maintenance mode',
     'category'            => 'tropk-core',
				'description'         => 'Toggle the WordPress .maintenance flag (writes/removes ABSPATH/.maintenance).',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'enabled' ],
					'properties' => [ 'enabled' => [ 'type' => 'boolean' ] ],
				],
				'execute_callback'    => static function ( array $input ): array {
					$file = wp_normalize_path( ABSPATH . '.maintenance' );
					if ( (bool) $input['enabled'] ) {
						file_put_contents( $file, '<?php $upgrading = ' . time() . ';' );
						return [ 'enabled' => true ];
					}
					if ( file_exists( $file ) ) {
						unlink( $file );
					}
					return [ 'enabled' => false ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-perf/flush-rewrite-rules',
			[
				'label'               => 'Performance: flush rewrite rules',
     'category'            => 'tropk-core',
				'description'         => 'Force WordPress to regenerate its rewrite rules.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					flush_rewrite_rules( false );
					return [ 'flushed' => true ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-perf/system-info',
			[
				'label'               => 'Performance: system info',
     'category'            => 'tropk-core',
				'description'         => 'Returns PHP/MySQL/WordPress versions, memory limits, max upload, time zone, active theme, hosting hints.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					global $wpdb, $wp_version;
					return [
						'wp_version'      => $wp_version,
						'php_version'     => PHP_VERSION,
						'mysql_version'   => $wpdb->db_version(),
						'memory_limit'    => ini_get( 'memory_limit' ),
						'max_execution'   => ini_get( 'max_execution_time' ),
						'upload_max'      => ini_get( 'upload_max_filesize' ),
						'post_max'        => ini_get( 'post_max_size' ),
						'timezone'        => wp_timezone_string(),
						'is_multisite'    => is_multisite(),
						'active_theme'    => wp_get_theme()->get_stylesheet(),
						'active_plugins'  => count( (array) get_option( 'active_plugins', [] ) ),
						'language'        => get_locale(),
						'home_url'        => home_url(),
						'site_url'        => site_url(),
					];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-perf/metrics',
			[
				'label'               => 'Performance: site metrics snapshot',
     'category'            => 'tropk-core',
				'description'         => 'Quick counts: published posts/pages, draft posts, comments, users, attachments, transient count, total DB size in MB.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					global $wpdb;
					$db_size = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = %s', DB_NAME ) );
					return [
						'posts_published' => (int) wp_count_posts( 'post' )->publish,
						'pages_published' => (int) wp_count_posts( 'page' )->publish,
						'posts_draft'     => (int) wp_count_posts( 'post' )->draft,
						'comments'        => (int) wp_count_comments()->approved,
						'comments_spam'   => (int) wp_count_comments()->spam,
						'users'           => (int) count_users()['total_users'],
						'attachments'     => (int) wp_count_posts( 'attachment' )->inherit,
						'transients'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%'" ),
						'db_size_mb'      => $db_size,
					];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);
	},
	20
);
