<?php
/**
 * Gutenberg block abilities for the Abilities API.
 *
 * Registers ~12 abilities under the `blocks/*` namespace for block-type
 * discovery, patterns, reusable blocks (wp_block CPT), block content
 * parsing and block-editor settings.
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
			'tropk-blocks/get-types',
			[
				'label'               => 'Blocks: list registered block types',
     'category'            => 'tropk-core',
				'description'         => 'Returns all block types registered server-side, with name, title, category and attributes schema.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					$registry = \WP_Block_Type_Registry::get_instance();
					$out      = [];
					foreach ( $registry->get_all_registered() as $name => $type ) {
						$out[] = [
							'name'       => $name,
							'title'      => $type->title ?? '',
							'category'   => $type->category ?? '',
							'icon'       => $type->icon ?? '',
							'keywords'   => $type->keywords ?? [],
							'attributes' => $type->attributes ?? [],
						];
					}
					return [ 'blocks' => $out, 'count' => count( $out ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-blocks/get-categories',
			[
				'label'               => 'Blocks: list block categories',
     'category'            => 'tropk-core',
				'description'         => 'List block categories used in the editor.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					$categories = (array) get_block_categories( get_post() );
					return [ 'categories' => $categories ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-blocks/get-patterns',
			[
				'label'               => 'Blocks: list block patterns',
     'category'            => 'tropk-core',
				'description'         => 'List registered block patterns.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					$patterns = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();
					$out      = [];
					foreach ( $patterns as $p ) {
						$out[] = [
							'name'       => $p['name'] ?? '',
							'title'      => $p['title'] ?? '',
							'categories' => $p['categories'] ?? [],
							'keywords'   => $p['keywords'] ?? [],
						];
					}
					return [ 'patterns' => $out, 'count' => count( $out ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-blocks/get-pattern-categories',
			[
				'label'               => 'Blocks: list pattern categories',
     'category'            => 'tropk-core',
				'description'         => 'List registered block pattern categories.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					$registry = \WP_Block_Pattern_Categories_Registry::get_instance();
					return [ 'categories' => $registry->get_all_registered() ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-blocks/parse',
			[
				'label'               => 'Blocks: parse block content',
     'category'            => 'tropk-core',
				'description'         => 'Parse a string of block markup into the structured block tree.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'content' ],
					'properties' => [ 'content' => [ 'type' => 'string' ] ],
				],
				'execute_callback'    => static function ( array $input ): array {
					return [ 'blocks' => parse_blocks( (string) $input['content'] ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-blocks/serialize',
			[
				'label'               => 'Blocks: serialize block tree',
     'category'            => 'tropk-core',
				'description'         => 'Serialize a block tree (array of blocks) back into the block markup string.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'blocks' ],
					'properties' => [ 'blocks' => [ 'type' => 'array' ] ],
				],
				'execute_callback'    => static function ( array $input ): array {
					return [ 'content' => serialize_blocks( (array) $input['blocks'] ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-blocks/get-reusable',
			[
				'label'               => 'Blocks: list reusable blocks',
     'category'            => 'tropk-core',
				'description'         => 'Lists wp_block (reusable block) posts.',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
						'search'   => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$args  = [
						'post_type'      => 'wp_block',
						'posts_per_page' => (int) ( $input['per_page'] ?? 20 ),
					];
					if ( ! empty( $input['search'] ) ) {
						$args['s'] = (string) $input['search'];
					}
					$query = new \WP_Query( $args );
					$out   = [];
					foreach ( $query->posts as $p ) {
						$out[] = [
							'id'       => $p->ID,
							'title'    => $p->post_title,
							'content'  => $p->post_content,
							'status'   => $p->post_status,
							'modified' => $p->post_modified_gmt,
						];
					}
					return [ 'reusable_blocks' => $out, 'total' => (int) $query->found_posts ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-blocks/create-reusable',
			[
				'label'               => 'Blocks: create reusable block',
     'category'            => 'tropk-core',
				'description'         => 'Create a new wp_block reusable block.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'title', 'content' ],
					'properties' => [
						'title'   => [ 'type' => 'string', 'minLength' => 1 ],
						'content' => [ 'type' => 'string', 'description' => 'Block markup.' ],
						'status'  => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'private' ], 'default' => 'publish' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$id = wp_insert_post(
						[
							'post_type'    => 'wp_block',
							'post_title'   => (string) $input['title'],
							'post_content' => (string) $input['content'],
							'post_status'  => (string) ( $input['status'] ?? 'publish' ),
						],
						true
					);
					if ( is_wp_error( $id ) ) {
						throw new \RuntimeException( $id->get_error_message() );
					}
					return [ 'created' => true, 'id' => (int) $id ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => false ] ],
			]
		);

		wp_register_ability(
			'tropk-blocks/update-reusable',
			[
				'label'               => 'Blocks: update reusable block',
     'category'            => 'tropk-core',
				'description'         => 'Update the title and/or content of an existing wp_block.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'id' ],
					'properties' => [
						'id'      => [ 'type' => 'integer', 'minimum' => 1 ],
						'title'   => [ 'type' => 'string' ],
						'content' => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$post = get_post( (int) $input['id'] );
					if ( ! $post || 'wp_block' !== $post->post_type ) {
						throw new \RuntimeException( 'Reusable block not found.' );
					}
					$args = [ 'ID' => (int) $input['id'] ];
					if ( isset( $input['title'] ) ) {
						$args['post_title'] = (string) $input['title'];
					}
					if ( isset( $input['content'] ) ) {
						$args['post_content'] = (string) $input['content'];
					}
					$updated = wp_update_post( $args, true );
					if ( is_wp_error( $updated ) ) {
						throw new \RuntimeException( $updated->get_error_message() );
					}
					return [ 'updated' => true, 'id' => (int) $input['id'] ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-blocks/delete-reusable',
			[
				'label'               => 'Blocks: delete reusable block',
     'category'            => 'tropk-core',
				'description'         => 'Delete a wp_block reusable block (trash by default; pass force=true to force-delete).',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'id' ],
					'properties' => [
						'id'    => [ 'type' => 'integer', 'minimum' => 1 ],
						'force' => [ 'type' => 'boolean', 'default' => false ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$ok = wp_delete_post( (int) $input['id'], (bool) ( $input['force'] ?? false ) );
					return [ 'deleted' => (bool) $ok, 'id' => (int) $input['id'] ];
				},
				'permission_callback' => static fn() => current_user_can( 'delete_posts' ),
				'meta'                => [ 'annotations' => [ 'destructive' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-blocks/get-editor-settings',
			[
				'label'               => 'Blocks: editor settings',
     'category'            => 'tropk-core',
				'description'         => 'Return the settings that would be passed to the block editor for a given post type (color palette, font sizes, supports, etc).',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'post_type' => [ 'type' => 'string', 'default' => 'post' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$post_type = (string) ( $input['post_type'] ?? 'post' );
					if ( ! post_type_exists( $post_type ) ) {
						throw new \RuntimeException( sprintf( 'Unknown post type "%s".', $post_type ) );
					}
					$context = \WP_Block_Editor_Context::class !== '' && class_exists( '\\WP_Block_Editor_Context' )
						? new \WP_Block_Editor_Context( [ 'post' => null, 'name' => 'core/edit-post' ] )
						: null;
					$settings = $context ? get_block_editor_settings( [], $context ) : [];
					return [ 'settings' => $settings ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-blocks/get-style-variations',
			[
				'label'               => 'Blocks: theme style variations',
     'category'            => 'tropk-core',
				'description'         => 'Returns the active theme\'s style variations (for FSE themes that ship multiple variants of theme.json).',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					if ( ! class_exists( '\\WP_Theme_JSON_Resolver' ) || ! method_exists( '\\WP_Theme_JSON_Resolver', 'get_style_variations' ) ) {
						return [ 'variations' => [], 'supported' => false ];
					}
					return [ 'variations' => \WP_Theme_JSON_Resolver::get_style_variations(), 'supported' => true ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_theme_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);
	},
	20
);
