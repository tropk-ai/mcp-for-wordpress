<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Acf\AcfRuntime;

/**
 * Manages individual ACF fields inside a field group via ACF's PHP API
 * (acf_get_fields / acf_get_field / acf_update_field / acf_delete_field).
 * Available on ACF free and Pro.
 */
final class AcfFieldAbility implements Ability {

	public function slug(): string {
		return 'acf-field';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Manage ACF fields', 'mcp-for-wordpress' ),
			'description'         => __( 'Create, read, update or delete individual ACF fields inside a field group. Supports all ACF field types (text, image, repeater, group, flexible_content, etc.) via the open `field` object. get-fields needs `parent_id` (the field-group key). Field ids are ACF keys like "field_abc123".', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'action' ],
				'properties'           => [
					'action'    => [
						'type' => 'string',
						'enum' => [ 'get-fields', 'get-field', 'create-field', 'update-field', 'delete-field', 'duplicate-field' ],
					],
					'field_id'  => [
						'type'        => 'string',
						'description' => 'Field key like "field_abc123" or numeric post ID. Required for get-field, update-field, delete-field.',
					],
					'parent_id' => [
						'type'        => 'string',
						'description' => 'Parent key — usually a field-group key like "group_abc123" (for top-level fields) OR another field key (for sub-fields under a repeater/group/flexible_content). Required for get-fields and create-field.',
					],
					'field'     => [
						'type'                 => 'object',
						'description'          => 'Field config: label, name, type (text|textarea|number|email|url|image|file|wysiwyg|select|checkbox|radio|true_false|date_picker|time_picker|date_time_picker|color_picker|google_map|repeater|group|flexible_content|relationship|post_object|taxonomy|user|...), plus type-specific keys (choices, sub_fields, layouts, min, max, required, default_value, ...). Required for create-field and update-field.',
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
		AcfRuntime::require_active();
		$action = (string) ( $input['action'] ?? '' );

		switch ( $action ) {
			case 'get-fields':
				$parent = (string) ( $input['parent_id'] ?? '' );
				if ( '' === $parent ) {
					throw new \RuntimeException( 'parent_id is required for get-fields.' );
				}
				return [ 'fields' => (array) acf_get_fields( $parent ) ];

			case 'get-field':
				$id    = (string) ( $input['field_id'] ?? '' );
				if ( '' === $id ) {
					throw new \RuntimeException( 'field_id is required for get-field.' );
				}
				$field = acf_get_field( $id );
				if ( ! is_array( $field ) ) {
					throw new \RuntimeException( sprintf( 'Field "%s" not found.', $id ) );
				}
				return [ 'field' => $field ];

			case 'create-field':
				$parent = (string) ( $input['parent_id'] ?? '' );
				$field  = (array) ( $input['field'] ?? [] );
				if ( '' === $parent || empty( $field ) ) {
					throw new \RuntimeException( 'parent_id and field are required for create-field.' );
				}
				$field['parent'] = $parent;
				$saved = acf_update_field( $field );
				if ( ! is_array( $saved ) ) {
					throw new \RuntimeException( 'ACF rejected the field payload.' );
				}
				return [ 'created' => true, 'field' => $saved ];

			case 'update-field':
				$id    = (string) ( $input['field_id'] ?? '' );
				$patch = (array) ( $input['field'] ?? [] );
				if ( '' === $id || empty( $patch ) ) {
					throw new \RuntimeException( 'field_id and field are required for update-field.' );
				}
				$current = acf_get_field( $id );
				if ( ! is_array( $current ) ) {
					throw new \RuntimeException( sprintf( 'Field "%s" not found.', $id ) );
				}
				$merged = array_merge( $current, $patch );
				// Preserve identity keys ACF uses to locate the row.
				$merged['key'] = $current['key'] ?? ( $merged['key'] ?? '' );
				$merged['ID']  = $current['ID']  ?? ( $merged['ID']  ?? 0 );
				$saved = acf_update_field( $merged );
				if ( ! is_array( $saved ) ) {
					throw new \RuntimeException( 'ACF rejected the field patch.' );
				}
				return [ 'updated' => true, 'field' => $saved ];

			case 'delete-field':
				$id = (string) ( $input['field_id'] ?? '' );
				if ( '' === $id ) {
					throw new \RuntimeException( 'field_id is required for delete-field.' );
				}
				$ok = (bool) acf_delete_field( $id );
				return [ 'deleted' => $ok ];

			case 'duplicate-field':
				$id = (string) ( $input['field_id'] ?? '' );
				if ( '' === $id ) {
					throw new \RuntimeException( 'field_id is required for duplicate-field.' );
				}
				$parent = (string) ( $input['parent_id'] ?? '' );
				if ( ! function_exists( 'acf_duplicate_field' ) ) {
					throw new \RuntimeException( 'acf_duplicate_field is unavailable on this ACF version.' );
				}
				$duplicate = acf_duplicate_field( $id, $parent );
				if ( ! is_array( $duplicate ) ) {
					throw new \RuntimeException( sprintf( 'ACF could not duplicate field "%s".', $id ) );
				}
				return [ 'duplicated' => true, 'field' => $duplicate ];
		}

		throw new \RuntimeException( sprintf( 'Unknown action "%s".', $action ) );
	}
}
