<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Acf\AngieAcfBridge;

final class AcfFieldGroupAbility implements Ability {

	public function slug(): string {
		return 'acf-field-group';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Manage ACF field groups', 'mcp-for-wordpress' ),
			'description'         => __( 'List, create, update or delete ACF field groups with their location rules and nested fields. Use `field_group.location` to bind the group to a post type, page, taxonomy term, user role, etc.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'action' ],
				'properties'           => [
					'action'      => [
						'type' => 'string',
						'enum' => [ 'get-field-groups', 'get-field-group', 'create-field-group', 'update-field-group', 'delete-field-group' ],
					],
					'group_id'    => [
						'type'        => 'string',
						'description' => 'Field group key like "group_abc123". Required for get-field-group, update-field-group, delete-field-group.',
					],
					'field_group' => [
						'type'                 => 'object',
						'description'          => 'Field group config: title, fields[], location[][] (array of arrays of {param, operator, value}), menu_order, position, style, label_placement, instruction_placement, hide_on_screen[], active, show_in_rest. Required for create-field-group and update-field-group.',
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
			case 'get-field-groups':
				return [ 'field_groups' => $bridge->request( 'GET', 'field-groups' ) ];

			case 'get-field-group':
				$id = (string) ( $input['group_id'] ?? '' );
				if ( '' === $id ) {
					throw new \RuntimeException( 'group_id is required for get-field-group.' );
				}
				return [ 'field_group' => $bridge->request( 'GET', 'field-groups/' . rawurlencode( $id ) ) ];

			case 'create-field-group':
				$body = (array) ( $input['field_group'] ?? [] );
				if ( empty( $body ) ) {
					throw new \RuntimeException( 'field_group is required for create-field-group.' );
				}
				return [ 'created' => true, 'result' => $bridge->request( 'POST', 'field-groups', $body ) ];

			case 'update-field-group':
				$id   = (string) ( $input['group_id'] ?? '' );
				$body = (array) ( $input['field_group'] ?? [] );
				if ( '' === $id || empty( $body ) ) {
					throw new \RuntimeException( 'group_id and field_group are required for update-field-group.' );
				}
				return [ 'updated' => true, 'result' => $bridge->request( 'PUT', 'field-groups/' . rawurlencode( $id ), $body ) ];

			case 'delete-field-group':
				$id = (string) ( $input['group_id'] ?? '' );
				if ( '' === $id ) {
					throw new \RuntimeException( 'group_id is required for delete-field-group.' );
				}
				return [ 'deleted' => true, 'result' => $bridge->request( 'DELETE', 'field-groups/' . rawurlencode( $id ) ) ];
		}

		throw new \RuntimeException( sprintf( 'Unknown action "%s".', $action ) );
	}
}
