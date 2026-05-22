<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Acf\AngieAcfBridge;

final class AcfFieldAbility implements Ability {

	public function slug(): string {
		return 'acf-field';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Manage ACF fields', 'mcp-for-wordpress' ),
			'description'         => __( 'Create, read, update or delete individual ACF fields inside a field group. Supports all ACF field types (text, image, repeater, group, flexible_content, etc.) via the open `field` object.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'action' ],
				'properties'           => [
					'action'    => [
						'type' => 'string',
						'enum' => [ 'get-fields', 'get-field', 'create-field', 'update-field', 'delete-field' ],
					],
					'field_id'  => [
						'type'        => 'string',
						'description' => 'Field key like "field_abc123". Required for get-field, update-field, delete-field.',
					],
					'parent_id' => [
						'type'        => 'string',
						'description' => 'Parent field-group key like "group_abc123". Required for create-field.',
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
		$bridge = new AngieAcfBridge();
		$action = (string) ( $input['action'] ?? '' );

		switch ( $action ) {
			case 'get-fields':
				return [ 'fields' => $bridge->request( 'GET', 'fields' ) ];

			case 'get-field':
				$id = (string) ( $input['field_id'] ?? '' );
				if ( '' === $id ) {
					throw new \RuntimeException( 'field_id is required for get-field.' );
				}
				return [ 'field' => $bridge->request( 'GET', 'fields/' . rawurlencode( $id ) ) ];

			case 'create-field':
				$parent = (string) ( $input['parent_id'] ?? '' );
				$field  = (array) ( $input['field'] ?? [] );
				if ( '' === $parent || empty( $field ) ) {
					throw new \RuntimeException( 'parent_id and field are required for create-field.' );
				}
				$field['parent'] = $parent;
				return [ 'created' => true, 'result' => $bridge->request( 'POST', 'fields', $field ) ];

			case 'update-field':
				$id    = (string) ( $input['field_id'] ?? '' );
				$field = (array) ( $input['field'] ?? [] );
				if ( '' === $id || empty( $field ) ) {
					throw new \RuntimeException( 'field_id and field are required for update-field.' );
				}
				return [ 'updated' => true, 'result' => $bridge->request( 'PUT', 'fields/' . rawurlencode( $id ), $field ) ];

			case 'delete-field':
				$id = (string) ( $input['field_id'] ?? '' );
				if ( '' === $id ) {
					throw new \RuntimeException( 'field_id is required for delete-field.' );
				}
				return [ 'deleted' => true, 'result' => $bridge->request( 'DELETE', 'fields/' . rawurlencode( $id ) ) ];
		}

		throw new \RuntimeException( sprintf( 'Unknown action "%s".', $action ) );
	}
}
