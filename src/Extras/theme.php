<?php
/**
 * Theme / FSE abilities for the Abilities API.
 *
 * Registers ~14 abilities under the `theme/*` namespace. Filesystem
 * read/write is restricted to files inside the active theme's stylesheet
 * directory and is gated on `edit_themes`.
 *
 * @package Tropk\Mcp\Extras
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'tropk_mcp_theme_safe_path' ) ) {
	/**
	 * Resolve a path inside the active theme, refusing traversal.
	 * Returns null when the resolved path escapes the theme dir.
	 */
	function tropk_mcp_theme_safe_path( string $relative ): ?string {
		$theme_dir = wp_normalize_path( get_stylesheet_directory() );
		$relative  = ltrim( wp_normalize_path( $relative ), '/' );
		$abs       = wp_normalize_path( $theme_dir . '/' . $relative );
		// Normalize . and ..
		$parts     = [];
		foreach ( explode( '/', $abs ) as $seg ) {
			if ( '..' === $seg ) {
				array_pop( $parts );
			} elseif ( '' !== $seg && '.' !== $seg ) {
				$parts[] = $seg;
			}
		}
		$resolved = '/' . implode( '/', $parts );
		if ( str_starts_with( $resolved, $theme_dir ) ) {
			return $resolved;
		}
		return null;
	}
}

add_action(
	'wp_abilities_api_init',
	static function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'tropk-theme/list',
			[
				'label'               => 'Themes: list',
     'category'            => 'tropk-core',
				'description'         => 'List all installed themes (name, version, template, child status, active state).',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					$themes = wp_get_themes();
					$active = wp_get_theme()->get_stylesheet();
					$out    = [];
					foreach ( $themes as $slug => $t ) {
						$out[] = [
							'slug'        => $slug,
							'name'        => $t->get( 'Name' ),
							'version'     => $t->get( 'Version' ),
							'template'    => $t->get( 'Template' ),
							'is_child'    => $t->parent() ? true : false,
							'active'      => $slug === $active,
							'block_theme' => method_exists( $t, 'is_block_theme' ) ? $t->is_block_theme() : false,
						];
					}
					return [ 'themes' => $out, 'active' => $active ];
				},
				'permission_callback' => static fn() => current_user_can( 'switch_themes' ) || current_user_can( 'edit_theme_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-theme/get-active',
			[
				'label'               => 'Themes: get active',
     'category'            => 'tropk-core',
				'description'         => 'Detailed info about the active theme.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					$t = wp_get_theme();
					return [
						'slug'        => $t->get_stylesheet(),
						'name'        => $t->get( 'Name' ),
						'version'     => $t->get( 'Version' ),
						'template'    => $t->get_template(),
						'parent'      => $t->parent() ? $t->parent()->get( 'Name' ) : null,
						'block_theme' => method_exists( $t, 'is_block_theme' ) ? $t->is_block_theme() : false,
						'directory'   => $t->get_stylesheet_directory(),
					];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_theme_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-theme/exists',
			[
				'label'               => 'Themes: check exists',
     'category'            => 'tropk-core',
				'description'         => 'Check whether a theme directory is installed.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'slug' ],
					'properties' => [ 'slug' => [ 'type' => 'string' ] ],
				],
				'execute_callback'    => static function ( array $input ): array {
					$t = wp_get_theme( (string) $input['slug'] );
					return [ 'exists' => $t->exists() ];
				},
				'permission_callback' => static fn() => current_user_can( 'switch_themes' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-theme/activate',
			[
				'label'               => 'Themes: activate',
     'category'            => 'tropk-core',
				'description'         => 'Switch the active theme. Caller must have switch_themes.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'slug' ],
					'properties' => [ 'slug' => [ 'type' => 'string' ] ],
				],
				'execute_callback'    => static function ( array $input ): array {
					$t = wp_get_theme( (string) $input['slug'] );
					if ( ! $t->exists() ) {
						throw new \RuntimeException( sprintf( 'Theme "%s" not installed.', (string) $input['slug'] ) );
					}
					switch_theme( $t->get_stylesheet() );
					return [ 'activated' => true, 'slug' => $t->get_stylesheet() ];
				},
				'permission_callback' => static fn() => current_user_can( 'switch_themes' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-theme/delete',
			[
				'label'               => 'Themes: delete',
     'category'            => 'tropk-core',
				'description'         => 'Delete an installed theme. The active theme cannot be deleted.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'slug' ],
					'properties' => [ 'slug' => [ 'type' => 'string' ] ],
				],
				'execute_callback'    => static function ( array $input ): array {
					$slug = (string) $input['slug'];
					if ( $slug === wp_get_theme()->get_stylesheet() ) {
						throw new \RuntimeException( 'Refusing to delete the active theme.' );
					}
					if ( ! function_exists( 'delete_theme' ) ) {
						require_once ABSPATH . 'wp-admin/includes/theme.php';
					}
					$result = delete_theme( $slug );
					if ( is_wp_error( $result ) ) {
						throw new \RuntimeException( $result->get_error_message() );
					}
					return [ 'deleted' => (bool) $result, 'slug' => $slug ];
				},
				'permission_callback' => static fn() => current_user_can( 'delete_themes' ),
				'meta'                => [ 'annotations' => [ 'destructive' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-theme/get-mods',
			[
				'label'               => 'Themes: get mods',
     'category'            => 'tropk-core',
				'description'         => 'Get all theme_mods for the active theme (Customizer settings).',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					$mods = get_theme_mods();
					return [ 'mods' => is_array( $mods ) ? $mods : [] ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_theme_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-theme/set-mod',
			[
				'label'               => 'Themes: set mod',
     'category'            => 'tropk-core',
				'description'         => 'Set or update a single theme_mod (Customizer setting) for the active theme.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'key', 'value' ],
					'properties' => [
						'key'   => [ 'type' => 'string' ],
						'value' => [],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					set_theme_mod( (string) $input['key'], $input['value'] );
					return [ 'updated' => true, 'key' => (string) $input['key'] ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_theme_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-theme/create-child',
			[
				'label'               => 'Themes: create child theme',
     'category'            => 'tropk-core',
				'description'         => 'Create a child theme of the active theme by writing style.css and functions.php in wp-content/themes/{slug}.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'slug', 'name' ],
					'properties' => [
						'slug' => [ 'type' => 'string', 'pattern' => '^[a-z0-9-]+$' ],
						'name' => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$parent     = wp_get_theme();
					$slug       = (string) $input['slug'];
					$name       = (string) $input['name'];
					$themes_dir = wp_normalize_path( WP_CONTENT_DIR . '/themes' );
					$child_dir  = $themes_dir . '/' . $slug;
					if ( is_dir( $child_dir ) ) {
						throw new \RuntimeException( sprintf( 'Theme directory "%s" already exists.', $slug ) );
					}
					if ( ! wp_mkdir_p( $child_dir ) ) {
						throw new \RuntimeException( 'Failed to create child theme directory.' );
					}
					$style = "/*\nTheme Name: {$name}\nTemplate: " . $parent->get_stylesheet() . "\nVersion: 1.0.0\n*/\n";
					file_put_contents( $child_dir . '/style.css', $style );
					file_put_contents( $child_dir . '/functions.php', "<?php\nadd_action( 'wp_enqueue_scripts', function () {\n\twp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );\n} );\n" );
					return [ 'created' => true, 'slug' => $slug, 'directory' => $child_dir ];
				},
				'permission_callback' => static fn() => current_user_can( 'install_themes' ) && current_user_can( 'edit_themes' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => false ] ],
			]
		);

		wp_register_ability(
			'tropk-theme/get-json',
			[
				'label'               => 'Themes: get theme.json',
     'category'            => 'tropk-core',
				'description'         => 'Read the active theme.json (resolved with user customizations).',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'origin' => [ 'type' => 'string', 'enum' => [ 'theme', 'user', 'default' ], 'default' => 'theme' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					if ( ! class_exists( '\\WP_Theme_JSON_Resolver' ) ) {
						return [ 'supported' => false ];
					}
					$origin = (string) ( $input['origin'] ?? 'theme' );
					$data   = match ( $origin ) {
						'user'    => \WP_Theme_JSON_Resolver::get_user_data(),
						'default' => \WP_Theme_JSON_Resolver::get_core_data(),
						default   => \WP_Theme_JSON_Resolver::get_theme_data(),
					};
					return [ 'supported' => true, 'origin' => $origin, 'theme_json' => $data->get_raw_data() ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_theme_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-theme/update-user-json',
			[
				'label'               => 'Themes: update user theme.json',
     'category'            => 'tropk-core',
				'description'         => 'Merge a theme.json delta into the user-level customizations (does NOT touch the theme files).',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'theme_json' ],
					'properties' => [
						'theme_json' => [ 'type' => 'object', 'additionalProperties' => true ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					if ( ! class_exists( '\\WP_Theme_JSON_Resolver' ) ) {
						throw new \RuntimeException( 'Block theme stack not available.' );
					}
					$post_id = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
					if ( ! $post_id ) {
						$post_id = wp_insert_post(
							[
								'post_type'    => 'wp_global_styles',
								'post_status'  => 'publish',
								'post_title'   => 'Custom Styles',
								'post_name'    => 'wp-global-styles-' . urlencode( wp_get_theme()->get_stylesheet() ),
								'post_content' => '{}',
							],
							true
						);
						if ( is_wp_error( $post_id ) ) {
							throw new \RuntimeException( $post_id->get_error_message() );
						}
					}
					$existing = json_decode( (string) get_post_field( 'post_content', (int) $post_id ), true );
					if ( ! is_array( $existing ) ) {
						$existing = [];
					}
					$merged = array_replace_recursive( $existing, (array) $input['theme_json'] );
					$ok     = wp_update_post(
						[
							'ID'           => (int) $post_id,
							'post_content' => wp_json_encode( $merged ),
						],
						true
					);
					if ( is_wp_error( $ok ) ) {
						throw new \RuntimeException( $ok->get_error_message() );
					}
					return [ 'updated' => true, 'post_id' => (int) $post_id ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_theme_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-theme/list-files',
			[
				'label'               => 'Themes: list active-theme files',
     'category'            => 'tropk-core',
				'description'         => 'List files inside the active theme directory (recursive, with size and mtime).',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'subdir' => [ 'type' => 'string', 'default' => '', 'description' => 'Relative subdirectory (default: theme root).' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$base = tropk_mcp_theme_safe_path( (string) ( $input['subdir'] ?? '' ) );
					if ( null === $base || ! is_dir( $base ) ) {
						throw new \RuntimeException( 'Path is outside the active theme or does not exist.' );
					}
					$iter  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS ) );
					$theme = wp_normalize_path( get_stylesheet_directory() );
					$out   = [];
					foreach ( $iter as $file ) {
						if ( $file->isFile() ) {
							$out[] = [
								'path'  => substr( wp_normalize_path( (string) $file->getRealPath() ), strlen( $theme ) + 1 ),
								'size'  => $file->getSize(),
								'mtime' => $file->getMTime(),
							];
						}
					}
					return [ 'files' => $out, 'count' => count( $out ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_themes' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-theme/read-file',
			[
				'label'               => 'Themes: read active-theme file',
     'category'            => 'tropk-core',
				'description'         => 'Read a file inside the active theme. Refuses any path that escapes the theme directory.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'path' ],
					'properties' => [ 'path' => [ 'type' => 'string' ] ],
				],
				'execute_callback'    => static function ( array $input ): array {
					$abs = tropk_mcp_theme_safe_path( (string) $input['path'] );
					if ( null === $abs || ! is_file( $abs ) ) {
						throw new \RuntimeException( 'File is outside the active theme or does not exist.' );
					}
					$contents = file_get_contents( $abs );
					if ( false === $contents ) {
						throw new \RuntimeException( 'Failed to read file.' );
					}
					return [ 'path' => (string) $input['path'], 'size' => strlen( $contents ), 'contents' => $contents ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_themes' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-theme/write-file',
			[
				'label'               => 'Themes: write active-theme file',
     'category'            => 'tropk-core',
				'description'         => 'Write to a file inside the active theme directory. Creates parent directories. Refuses .php writes unless `allow_php` is true; refuses any path that escapes the theme directory.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'path', 'contents' ],
					'properties' => [
						'path'      => [ 'type' => 'string' ],
						'contents'  => [ 'type' => 'string' ],
						'allow_php' => [ 'type' => 'boolean', 'default' => false ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$path = (string) $input['path'];
					if ( ! (bool) ( $input['allow_php'] ?? false ) && preg_match( '/\.(php|phtml|phar)$/i', $path ) ) {
						throw new \RuntimeException( 'PHP writes require allow_php=true.' );
					}
					$abs = tropk_mcp_theme_safe_path( $path );
					if ( null === $abs ) {
						throw new \RuntimeException( 'Path escapes the active theme directory.' );
					}
					$dir = dirname( $abs );
					if ( ! is_dir( $dir ) ) {
						wp_mkdir_p( $dir );
					}
					$bytes = file_put_contents( $abs, (string) $input['contents'] );
					if ( false === $bytes ) {
						throw new \RuntimeException( 'Failed to write file.' );
					}
					return [ 'written' => true, 'path' => $path, 'bytes' => $bytes ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_themes' ),
				'meta'                => [ 'annotations' => [ 'destructive' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-theme/get-templates',
			[
				'label'               => 'Themes: list templates',
     'category'            => 'tropk-core',
				'description'         => 'List page templates and (for FSE) block templates registered by the active theme.',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'post_type' => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$theme = wp_get_theme();
					$page  = $theme->get_page_templates( null, (string) ( $input['post_type'] ?? '' ) ?: null );
					$block = [];
					if ( method_exists( $theme, 'is_block_theme' ) && $theme->is_block_theme() ) {
						$queries = get_block_templates( [ 'theme' => $theme->get_stylesheet() ], 'wp_template' );
						foreach ( $queries as $tpl ) {
							$block[] = [ 'slug' => $tpl->slug, 'title' => $tpl->title, 'description' => $tpl->description ];
						}
					}
					return [ 'page_templates' => $page, 'block_templates' => $block ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_theme_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-theme/get-template-parts',
			[
				'label'               => 'Themes: list block-template parts',
     'category'            => 'tropk-core',
				'description'         => 'Lists wp_template_part entries for the active block theme.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					$parts = [];
					if ( function_exists( 'get_block_templates' ) ) {
						foreach ( get_block_templates( [], 'wp_template_part' ) as $part ) {
							$parts[] = [
								'slug'        => $part->slug,
								'title'       => $part->title,
								'area'        => $part->area ?? '',
								'description' => $part->description,
							];
						}
					}
					return [ 'template_parts' => $parts ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_theme_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);
	},
	20
);
