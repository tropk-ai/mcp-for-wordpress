<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Acf\AcfRuntime;

/**
 * Manages custom post types via ACF Pro 6.1+'s native PHP API
 * (acf_get_acf_post_types / acf_get_post_type / acf_update_post_type /
 * acf_delete_post_type). Throws a clear error if ACF Pro 6.1+ isn't
 * present.
 */
final class AcfPostTypeAbility implements Ability {
	use AcfSchemaAdminActions;

	public function slug(): string {
		return 'acf-post-type';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Manage ACF custom post types', 'mcp-for-wordpress' ),
			'description'         => __( 'List, create, update or delete custom post types via ACF (requires ACF Pro 6.1+). `config` accepts all standard register_post_type args wrapped in ACF\'s schema: `post_type` (slug), `title`, `labels.name`, `labels.singular_name`, plus supports[], hierarchical, public, show_ui, has_archive, taxonomies, capabilities, rewrite, show_in_rest, menu_icon, etc. Identifiers accept the slug (e.g. "product"), the ACF key (e.g. "post_type_xyz"), or the numeric ACF post ID.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'action' ],
				'properties'           => [
					'action'    => [
						'type' => 'string',
						'enum' => [
							'get-post-types', 'get-post-type', 'create-post-type', 'update-post-type', 'delete-post-type',
							'activate-post-type', 'deactivate-post-type',
							'trash-post-type', 'untrash-post-type',
							'duplicate-post-type',
							'export-post-type-as-php', 'import-post-type',
						],
					],
					'post_type' => [
						'type'        => 'string',
						'description' => 'Post type slug like "product", ACF key like "post_type_xyz", or numeric ACF post ID. Required for get-post-type, update-post-type, delete-post-type, activate/deactivate, trash/untrash, duplicate, export.',
					],
					'config'    => [
						'type'                 => 'object',
						'description'          => 'Post type configuration. Accepts every register_post_type() arg plus ACF schema wrappers: post_type (slug), title, description, labels{ name, singular_name, menu_name, name_admin_bar, add_new, add_new_item, edit_item, new_item, view_item, view_items, search_items, not_found, not_found_in_trash, archives, attributes, parent_item_colon, all_items, insert_into_item, uploaded_to_this_item, featured_image, set_featured_image, remove_featured_image, use_featured_image, filter_items_list, items_list_navigation, items_list }, public, hierarchical, exclude_from_search, publicly_queryable, show_ui, show_in_menu, menu_position, menu_icon, show_in_nav_menus, show_in_admin_bar, show_in_rest, rest_base, rest_namespace, rest_controller_class, has_archive, archive_template, rewrite{ with_front, slug, feeds, pages }, query_var, can_export, delete_with_user, supports[] (title|editor|thumbnail|excerpt|trackbacks|custom-fields|comments|revisions|page-attributes|post-formats|author), taxonomies[], capability_type, capabilities{}, map_meta_cap, template[], template_lock, enter_title_here, advanced_configuration, active. Required for create-post-type, update-post-type and import-post-type.',
						'additionalProperties' => true,
					],
				],
			],
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				],
				'show_in_rest' => true,
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function execute( array $input ): array {
		AcfRuntime::require_pro_post_types();
		$action = (string) ( $input['action'] ?? '' );

		switch ( $action ) {
			case 'get-post-types':
				return [ 'post_types' => (array) acf_get_acf_post_types() ];

			case 'get-post-type':
				$key  = $this->require_id( $input, 'post_type', 'get-post-type' );
				$post = acf_get_post_type( $key );
				if ( ! is_array( $post ) ) {
					throw new \RuntimeException( sprintf( 'ACF post type "%s" not found.', $key ) );
				}
				return [ 'post_type' => $post ];

			case 'create-post-type':
				$config = (array) ( $input['config'] ?? [] );
				if ( empty( $config ) ) {
					throw new \RuntimeException( 'config is required for create-post-type.' );
				}
				$saved = acf_update_post_type( $config );
				if ( ! is_array( $saved ) ) {
					throw new \RuntimeException( 'ACF rejected the post type payload.' );
				}
				return [ 'created' => true, 'post_type' => $saved ];

			case 'update-post-type':
				$key    = $this->require_id( $input, 'post_type', 'update-post-type' );
				$patch  = (array) ( $input['config'] ?? [] );
				if ( empty( $patch ) ) {
					throw new \RuntimeException( 'config is required for update-post-type.' );
				}
				$current = acf_get_post_type( $key );
				if ( ! is_array( $current ) ) {
					throw new \RuntimeException( sprintf( 'ACF post type "%s" not found.', $key ) );
				}
				$merged       = array_merge( $current, $patch );
				$merged['key'] = $current['key'] ?? ( $merged['key'] ?? '' );
				$merged['ID']  = $current['ID']  ?? ( $merged['ID']  ?? 0 );
				$saved = acf_update_post_type( $merged );
				if ( ! is_array( $saved ) ) {
					throw new \RuntimeException( 'ACF rejected the post type patch.' );
				}
				return [ 'updated' => true, 'post_type' => $saved ];

			case 'delete-post-type':
				$key = $this->require_id( $input, 'post_type', 'delete-post-type' );
				$ok  = (bool) acf_delete_post_type( $key );
				return [ 'deleted' => $ok ];

			case 'activate-post-type':
				return $this->activate_entity( 'post-type', $this->require_id( $input, 'post_type', $action ) );

			case 'deactivate-post-type':
				return $this->deactivate_entity( 'post-type', $this->require_id( $input, 'post_type', $action ) );

			case 'trash-post-type':
				return $this->trash_entity( 'post-type', $this->require_id( $input, 'post_type', $action ) );

			case 'untrash-post-type':
				return $this->untrash_entity( 'post-type', $this->require_id( $input, 'post_type', $action ) );

			case 'duplicate-post-type':
				return $this->duplicate_entity( 'post-type', $this->require_id( $input, 'post_type', $action ) );

			case 'export-post-type-as-php':
				return $this->export_entity_as_php( 'post-type', $this->require_id( $input, 'post_type', $action ) );

			case 'import-post-type':
				$body = (array) ( $input['config'] ?? [] );
				if ( empty( $body ) ) {
					throw new \RuntimeException( 'config is required for import-post-type.' );
				}
				return $this->import_entity( 'post-type', $body );
		}

		throw new \RuntimeException( sprintf( 'Unknown action "%s".', $action ) );
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function require_id( array $input, string $key, string $action ): string {
		$id = (string) ( $input[ $key ] ?? '' );
		if ( '' === $id ) {
			throw new \RuntimeException( sprintf( '%s is required for %s.', $key, $action ) );
		}
		return $id;
	}
}
