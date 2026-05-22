<?php
/**
 * Security / site-health abilities for the Abilities API.
 *
 * Registers ~7 abilities under the `security/*` namespace covering the
 * WP Site Health framework, debug log inspection, update checks, core
 * file integrity, failed-login probing and file-permission scans.
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
			'tropk-security/site-health',
			[
				'label'               => 'Security: run Site Health checks',
     'category'            => 'tropk-core',
				'description'         => 'Runs the WP Site Health "direct" tests and returns their results.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					if ( ! class_exists( 'WP_Site_Health' ) ) {
						require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
					}
					$health = \WP_Site_Health::get_instance();
					$tests  = \WP_Site_Health::get_tests();
					$out    = [];
					foreach ( $tests['direct'] ?? [] as $key => $test ) {
						if ( empty( $test['test'] ) ) {
							continue;
						}
						$method = 'get_test_' . str_replace( '-', '_', (string) $test['test'] );
						if ( method_exists( $health, $method ) ) {
							$result = $health->$method();
							$out[]  = [
								'test'        => $key,
								'label'       => $result['label'] ?? '',
         'category'            => 'tropk-core',
								'status'      => $result['status'] ?? '',
								'badge_color' => $result['badge']['color'] ?? '',
							];
						}
					}
					return [ 'tests' => $out, 'count' => count( $out ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'view_site_health_checks' ) || current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-security/check-updates',
			[
				'label'               => 'Security: check pending updates',
     'category'            => 'tropk-core',
				'description'         => 'Returns core, plugin and theme updates currently pending.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					wp_version_check();
					wp_update_plugins();
					wp_update_themes();
					$core    = get_core_updates();
					$plugins = get_plugin_updates();
					$themes  = get_theme_updates();
					$p       = [];
					foreach ( $plugins as $file => $info ) {
						$p[] = [
							'file'        => $file,
							'name'        => $info->Name ?? $file,
							'old_version' => $info->Version ?? '',
							'new_version' => $info->update->new_version ?? '',
						];
					}
					$t = [];
					foreach ( $themes as $slug => $info ) {
						$t[] = [
							'slug'        => $slug,
							'name'        => $info->get( 'Name' ),
							'old_version' => $info->get( 'Version' ),
							'new_version' => $info->update['new_version'] ?? '',
						];
					}
					return [
						'core'    => is_array( $core ) && ! empty( $core ) ? [ 'available' => true, 'version' => $core[0]->version ?? '' ] : [ 'available' => false ],
						'plugins' => $p,
						'themes'  => $t,
					];
				},
				'permission_callback' => static fn() => current_user_can( 'update_core' ) || current_user_can( 'update_plugins' ) || current_user_can( 'update_themes' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-security/get-debug-log',
			[
				'label'               => 'Security: read debug.log tail',
     'category'            => 'tropk-core',
				'description'         => 'Returns the last N lines of WP_DEBUG_LOG when debug.log exists.',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'lines' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 5000, 'default' => 200 ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$log_path = defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
					if ( ! is_string( $log_path ) || ! is_file( $log_path ) ) {
						return [ 'exists' => false, 'lines' => [] ];
					}
					$lines = (int) ( $input['lines'] ?? 200 );
					$fp    = fopen( $log_path, 'r' );
					if ( ! $fp ) {
						throw new \RuntimeException( 'Cannot open debug log.' );
					}
					$buffer = [];
					while ( ! feof( $fp ) ) {
						$line = fgets( $fp );
						if ( false === $line ) {
							break;
						}
						$buffer[] = rtrim( $line, "\r\n" );
						if ( count( $buffer ) > $lines ) {
							array_shift( $buffer );
						}
					}
					fclose( $fp );
					return [ 'exists' => true, 'lines' => $buffer, 'path' => $log_path ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-security/verify-core-files',
			[
				'label'               => 'Security: verify core file integrity',
     'category'            => 'tropk-core',
				'description'         => 'Compare a sample of core .php files against the WordPress checksums API.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					global $wp_version;
					if ( ! function_exists( 'get_core_checksums' ) ) {
						require_once ABSPATH . 'wp-admin/includes/update.php';
					}
					$locale    = get_locale();
					$checksums = get_core_checksums( $wp_version, $locale );
					if ( ! is_array( $checksums ) ) {
						$checksums = get_core_checksums( $wp_version, 'en_US' );
					}
					if ( ! is_array( $checksums ) ) {
						throw new \RuntimeException( 'Could not fetch checksums.' );
					}
					$bad = [];
					$checked = 0;
					foreach ( $checksums as $file => $hash ) {
						if ( str_starts_with( $file, 'wp-content/' ) ) {
							continue;
						}
						$abs = ABSPATH . $file;
						if ( ! file_exists( $abs ) ) {
							$bad[] = [ 'file' => $file, 'status' => 'missing' ];
							continue;
						}
						++$checked;
						if ( md5_file( $abs ) !== $hash ) {
							$bad[] = [ 'file' => $file, 'status' => 'modified' ];
						}
					}
					return [ 'version' => $wp_version, 'checked' => $checked, 'issues' => $bad ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-security/scan-permissions',
			[
				'label'               => 'Security: scan filesystem permissions',
     'category'            => 'tropk-core',
				'description'         => 'Check permissions of common WordPress paths (wp-config.php, .htaccess, wp-content, uploads). Flags world-writable items.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					$paths   = [
						'wp-config.php' => ABSPATH . 'wp-config.php',
						'.htaccess'     => ABSPATH . '.htaccess',
						'wp-content'    => WP_CONTENT_DIR,
						'uploads'       => wp_upload_dir()['basedir'] ?? '',
						'plugins'       => WP_PLUGIN_DIR,
					];
					$out     = [];
					foreach ( $paths as $name => $path ) {
						if ( '' === $path || ! file_exists( $path ) ) {
							continue;
						}
						$perms = substr( sprintf( '%o', fileperms( $path ) ), -4 );
						$out[] = [
							'name'           => $name,
							'path'           => $path,
							'perms'          => $perms,
							'world_writable' => ( fileperms( $path ) & 0o002 ) !== 0,
						];
					}
					return [ 'items' => $out ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-security/get-version-info',
			[
				'label'               => 'Security: version info',
     'category'            => 'tropk-core',
				'description'         => 'Returns WordPress, PHP and DB versions plus the number of pending updates.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					global $wp_version, $wpdb;
					$core_updates = function_exists( 'get_core_updates' ) ? (array) get_core_updates() : [];
					return [
						'wp_version'        => $wp_version,
						'php_version'       => PHP_VERSION,
						'mysql_version'     => $wpdb->db_version(),
						'pending_core'      => count( $core_updates ),
						'pending_plugins'   => function_exists( 'get_plugin_updates' ) ? count( (array) get_plugin_updates() ) : 0,
						'pending_themes'    => function_exists( 'get_theme_updates' ) ? count( (array) get_theme_updates() ) : 0,
					];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-security/failed-logins',
			[
				'label'               => 'Security: list failed login attempts',
     'category'            => 'tropk-core',
				'description'         => 'Reads failed login attempts from the user_meta key our auth audit table stores (only when our Auth audit is active).',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 50 ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					global $wpdb;
					$audit_table = $wpdb->prefix . 'tropk_mcp_audit';
					$count_table = (int) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $audit_table ) );
					if ( ! $count_table ) {
						return [ 'available' => false, 'reason' => 'tropk-mcp audit table not present.' ];
					}
					$limit = (int) ( $input['limit'] ?? 50 );
					$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$audit_table` WHERE event_type LIKE %s ORDER BY created_at DESC LIMIT %d", 'auth.fail%', $limit ), ARRAY_A );
					return [ 'available' => true, 'rows' => $rows ?: [], 'count' => $rows ? count( $rows ) : 0 ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);
	},
	20
);
