<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Acf\AcfRuntime;

/**
 * Manages ACF field groups via ACF's own PHP API (acf_get_field_groups
 * / acf_get_field_group / acf_update_field_group / acf_delete_field_group).
 * Available on ACF free and Pro.
 */
final class AcfFieldGroupAbility implements Ability {
	use AcfSchemaAdminActions;

	public function slug(): string {
		return 'acf-field-group';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Manage ACF field groups', 'mcp-for-wordpress' ),
			'description'         => __( 'List, create, update or delete ACF field groups with their location rules and nested fields. Use `field_group.location` to bind the group to a post type, page, taxonomy term, user role, etc. Group ids are ACF keys like "group_abc123" — list-field-groups returns each group\'s key.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'action' ],
				'properties'           => [
					'action'      => [
						'type' => 'string',
						'enum' => [
							'get-field-groups', 'get-field-group', 'create-field-group', 'update-field-group', 'delete-field-group',
							'activate-field-group', 'deactivate-field-group',
							'trash-field-group', 'untrash-field-group',
							'duplicate-field-group',
							'export-field-group-as-php', 'import-field-group',
						],
					],
					'group_id'    => [
						'type'        => 'string',
						'description' => 'Field group key like "group_abc123" or numeric post ID. Required for get-field-group, update-field-group, delete-field-group, activate/deactivate, trash/untrash, duplicate, export.',
					],
					'field_group' => [
						'type'                 => 'object',
						'description'          => 'Field group config: title, fields[], location[][] (array of arrays of {param, operator, value}), menu_order, position (high|normal|side|acf_after_title), style (default|seamless), label_placement (top|left), instruction_placement (label|field), hide_on_screen[] (permalink|the_content|excerpt|discussion|comments|revisions|slug|author|format|page_attributes|featured_image|categories|tags|send-trackbacks), active, show_in_rest. Required for create-field-group, update-field-group and import-field-group.',
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
			case 'get-field-groups':
				return [ 'field_groups' => (array) acf_get_field_groups() ];

			case 'get-field-group':
				$id    = $this->require_id( $input, 'group_id', 'get-field-group' );
				$group = acf_get_field_group( $id );
				if ( ! is_array( $group ) ) {
					throw new \RuntimeException( sprintf( 'Field group "%s" not found.', $id ) );
				}
				return [ 'field_group' => $group ];

			case 'create-field-group':
				$body = (array) ( $input['field_group'] ?? [] );
				if ( empty( $body ) ) {
					throw new \RuntimeException( 'field_group is required for create-field-group.' );
				}
				$saved = acf_update_field_group( $body );
				if ( ! is_array( $saved ) ) {
					throw new \RuntimeException( 'ACF rejected the field group payload.' );
				}
				return [ 'created' => true, 'field_group' => $saved ];

			case 'update-field-group':
				$id  = $this->require_id( $input, 'group_id', 'update-field-group' );
				$body = (array) ( $input['field_group'] ?? [] );
				if ( empty( $body ) ) {
					throw new \RuntimeException( 'field_group is required for update-field-group.' );
				}
				$current = acf_get_field_group( $id );
				if ( ! is_array( $current ) ) {
					throw new \RuntimeException( sprintf( 'Field group "%s" not found.', $id ) );
				}
				// Merge caller's patch over the current group so omitted keys
				// retain their existing value (matches the ACF admin "save"
				// semantics rather than a full replace).
				$merged       = array_merge( $current, $body );
				$merged['key']  = $current['key']  ?? ( $merged['key']  ?? '' );
				$merged['ID']   = $current['ID']   ?? ( $merged['ID']   ?? 0 );
				$saved = acf_update_field_group( $merged );
				if ( ! is_array( $saved ) ) {
					throw new \RuntimeException( 'ACF rejected the field group patch.' );
				}
				return [ 'updated' => true, 'field_group' => $saved ];

			case 'delete-field-group':
				$id = $this->require_id( $input, 'group_id', 'delete-field-group' );
				$ok = (bool) acf_delete_field_group( $id );
				return [ 'deleted' => $ok ];

			case 'activate-field-group':
				return $this->activate_entity( 'field-group', $this->require_id( $input, 'group_id', $action ) );

			case 'deactivate-field-group':
				return $this->deactivate_entity( 'field-group', $this->require_id( $input, 'group_id', $action ) );

			case 'trash-field-group':
				return $this->trash_entity( 'field-group', $this->require_id( $input, 'group_id', $action ) );

			case 'untrash-field-group':
				return $this->untrash_entity( 'field-group', $this->require_id( $input, 'group_id', $action ) );

			case 'duplicate-field-group':
				return $this->duplicate_entity( 'field-group', $this->require_id( $input, 'group_id', $action ) );

			case 'export-field-group-as-php':
				return $this->export_entity_as_php( 'field-group', $this->require_id( $input, 'group_id', $action ) );

			case 'import-field-group':
				$body = (array) ( $input['field_group'] ?? [] );
				if ( empty( $body ) ) {
					throw new \RuntimeException( 'field_group is required for import-field-group.' );
				}
				return $this->import_entity( 'field-group', $body );
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
