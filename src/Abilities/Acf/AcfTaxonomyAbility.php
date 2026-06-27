<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Acf\AcfRuntime;

/**
 * Manages custom taxonomies via ACF Pro 6.1+'s native PHP API
 * (acf_get_acf_taxonomies / acf_get_taxonomy / acf_update_taxonomy /
 * acf_delete_taxonomy). Throws a clear error if ACF Pro 6.1+ isn't
 * present.
 *
 * Note: ACF's `acf_get_taxonomy()` (from acf-taxonomy-functions.php)
 * returns the ACF taxonomy definition, NOT WP_Term — that's
 * `get_taxonomy()` in core, which we do not call.
 */
final class AcfTaxonomyAbility implements Ability {
	use AcfSchemaAdminActions;

	public function slug(): string {
		return 'acf-taxonomy';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Manage ACF custom taxonomies', 'mcp-for-wordpress' ),
			'description'         => __( 'List, create, update or delete custom taxonomies via ACF (requires ACF Pro 6.1+). `config` accepts standard register_taxonomy args plus ACF-specific keys. Identifiers accept the slug (e.g. "product_category"), the ACF key (e.g. "taxonomy_abc"), or the numeric ACF post ID.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'action' ],
				'properties'           => [
					'action'   => [
						'type' => 'string',
						'enum' => [
							'get-taxonomies', 'get-taxonomy', 'create-taxonomy', 'update-taxonomy', 'delete-taxonomy',
							'activate-taxonomy', 'deactivate-taxonomy',
							'trash-taxonomy', 'untrash-taxonomy',
							'duplicate-taxonomy',
							'export-taxonomy-as-php', 'import-taxonomy',
						],
					],
					'taxonomy' => [
						'type'        => 'string',
						'description' => 'Taxonomy slug like "product_category", ACF key like "taxonomy_abc", or numeric ACF post ID. Required for get-taxonomy, update-taxonomy, delete-taxonomy, activate/deactivate, trash/untrash, duplicate, export.',
					],
					'config'   => [
						'type'                 => 'object',
						'description'          => 'Taxonomy configuration. Accepts every register_taxonomy() arg: taxonomy (slug), object_type[] (post types this taxonomy is attached to), labels{ name, singular_name, menu_name, all_items, edit_item, view_item, update_item, add_new_item, new_item_name, parent_item, parent_item_colon, search_items, popular_items, separate_items_with_commas, add_or_remove_items, choose_from_most_used, not_found, no_terms, name_field_description, parent_field_description, slug_field_description, desc_field_description, filter_by_item, items_list_navigation, items_list }, description, public, publicly_queryable, hierarchical, show_ui, show_in_menu, show_in_nav_menus, show_in_rest, rest_base, rest_namespace, rest_controller_class, show_tagcloud, show_in_quick_edit, show_admin_column, query_var, rewrite{ slug, with_front, hierarchical }, capabilities{ manage_terms, edit_terms, delete_terms, assign_terms }, sort, default_term, active. Required for create-taxonomy, update-taxonomy and import-taxonomy.',
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
		AcfRuntime::require_pro_taxonomies();
		$action = (string) ( $input['action'] ?? '' );

		switch ( $action ) {
			case 'get-taxonomies':
				return [ 'taxonomies' => (array) acf_get_acf_taxonomies() ];

			case 'get-taxonomy':
				$key = $this->require_id( $input, 'taxonomy', 'get-taxonomy' );
				$tax = acf_get_taxonomy( $key );
				if ( ! is_array( $tax ) ) {
					throw new \RuntimeException( sprintf( 'ACF taxonomy "%s" not found.', $key ) );
				}
				return [ 'taxonomy' => $tax ];

			case 'create-taxonomy':
				$config = (array) ( $input['config'] ?? [] );
				if ( empty( $config ) ) {
					throw new \RuntimeException( 'config is required for create-taxonomy.' );
				}
				$saved = acf_update_taxonomy( $config );
				if ( ! is_array( $saved ) ) {
					throw new \RuntimeException( 'ACF rejected the taxonomy payload.' );
				}
				return [ 'created' => true, 'taxonomy' => $saved ];

			case 'update-taxonomy':
				$key   = $this->require_id( $input, 'taxonomy', 'update-taxonomy' );
				$patch = (array) ( $input['config'] ?? [] );
				if ( empty( $patch ) ) {
					throw new \RuntimeException( 'config is required for update-taxonomy.' );
				}
				$current = acf_get_taxonomy( $key );
				if ( ! is_array( $current ) ) {
					throw new \RuntimeException( sprintf( 'ACF taxonomy "%s" not found.', $key ) );
				}
				$merged       = array_merge( $current, $patch );
				$merged['key'] = $current['key'] ?? ( $merged['key'] ?? '' );
				$merged['ID']  = $current['ID']  ?? ( $merged['ID']  ?? 0 );
				$saved = acf_update_taxonomy( $merged );
				if ( ! is_array( $saved ) ) {
					throw new \RuntimeException( 'ACF rejected the taxonomy patch.' );
				}
				return [ 'updated' => true, 'taxonomy' => $saved ];

			case 'delete-taxonomy':
				$key = $this->require_id( $input, 'taxonomy', 'delete-taxonomy' );
				$ok  = (bool) acf_delete_taxonomy( $key );
				return [ 'deleted' => $ok ];

			case 'activate-taxonomy':
				return $this->activate_entity( 'taxonomy', $this->require_id( $input, 'taxonomy', $action ) );

			case 'deactivate-taxonomy':
				return $this->deactivate_entity( 'taxonomy', $this->require_id( $input, 'taxonomy', $action ) );

			case 'trash-taxonomy':
				return $this->trash_entity( 'taxonomy', $this->require_id( $input, 'taxonomy', $action ) );

			case 'untrash-taxonomy':
				return $this->untrash_entity( 'taxonomy', $this->require_id( $input, 'taxonomy', $action ) );

			case 'duplicate-taxonomy':
				return $this->duplicate_entity( 'taxonomy', $this->require_id( $input, 'taxonomy', $action ) );

			case 'export-taxonomy-as-php':
				return $this->export_entity_as_php( 'taxonomy', $this->require_id( $input, 'taxonomy', $action ) );

			case 'import-taxonomy':
				$body = (array) ( $input['config'] ?? [] );
				if ( empty( $body ) ) {
					throw new \RuntimeException( 'config is required for import-taxonomy.' );
				}
				return $this->import_entity( 'taxonomy', $body );
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
