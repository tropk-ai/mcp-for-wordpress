<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Acf\AcfRuntime;

/**
 * Sync ACF field groups with the local JSON folder (`acf-json/` in the
 * active theme by default — the canonical pattern for putting field
 * groups under version control).
 *
 * - list-local-json   → JSON files currently on disk + the load paths
 * - list-local-groups → ALL locally-registered field groups (JSON +
 *                       code via acf_add_local_field_group)
 * - save-to-json      → force-rewrite a field group's JSON file
 * - get-save-path     → where ACF will write new JSON
 */
final class AcfLocalJsonAbility implements Ability {

	public function slug(): string {
		return 'acf-local-json';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Sync ACF field groups with local JSON', 'mcp-for-wordpress' ),
			'description'         => __( 'Inspect and sync ACF field groups with the `acf-json/` folder in the active theme. Pair with the version-controlled JSON workflow: edit in admin → ACF writes JSON → commit. The agent can also force a write here.', 'mcp-for-wordpress' ),
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
							'list-local-json', 'list-local-groups', 'save-to-json',
							'get-load-paths', 'get-save-path',
							'is-local-field-group', 'is-local-field',
						],
					],
					'group_id' => [
						'type'        => 'string',
						'description' => 'Field group key like "group_abc123" or numeric post ID. Required for save-to-json and is-local-field-group.',
					],
					'field_id' => [
						'type'        => 'string',
						'description' => 'Field key like "field_abc123". Required for is-local-field.',
					],
				],
			],
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
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
			case 'list-local-json':
				if ( ! function_exists( 'acf_get_local_json_files' ) ) {
					throw new \RuntimeException( 'acf_get_local_json_files is unavailable on this ACF version.' );
				}
				return [
					'files'      => (array) acf_get_local_json_files(),
					'load_paths' => $this->load_paths(),
					'save_path'  => $this->save_path(),
				];

			case 'list-local-groups':
				if ( ! function_exists( 'acf_get_local_field_groups' ) ) {
					throw new \RuntimeException( 'acf_get_local_field_groups is unavailable on this ACF version.' );
				}
				$groups = (array) acf_get_local_field_groups();
				return [ 'field_groups' => array_values( $groups ), 'count' => count( $groups ) ];

			case 'save-to-json':
				$id = (string) ( $input['group_id'] ?? '' );
				if ( '' === $id ) {
					throw new \RuntimeException( 'group_id is required for save-to-json.' );
				}
				$group = acf_get_field_group( $id );
				if ( ! is_array( $group ) ) {
					throw new \RuntimeException( sprintf( 'Field group "%s" not found.', $id ) );
				}
				if ( ! function_exists( 'acf_write_json_field_group' ) ) {
					throw new \RuntimeException( 'acf_write_json_field_group is unavailable on this ACF version.' );
				}
				// ACF's writer reads sub-fields from the live store; load them now.
				$group['fields'] = function_exists( 'acf_get_fields' ) ? (array) acf_get_fields( $group ) : [];
				$ok = (bool) acf_write_json_field_group( $group );
				return [ 'written' => $ok, 'path' => $this->save_path() ];

			case 'get-load-paths':
				return [ 'load_paths' => $this->load_paths() ];

			case 'get-save-path':
				return [ 'save_path' => $this->save_path() ];

			case 'is-local-field-group':
				$id = (string) ( $input['group_id'] ?? '' );
				if ( '' === $id ) {
					throw new \RuntimeException( 'group_id is required for is-local-field-group.' );
				}
				return [ 'is_local' => (bool) acf_is_local_field_group( $id ) ];

			case 'is-local-field':
				$id = (string) ( $input['field_id'] ?? '' );
				if ( '' === $id ) {
					throw new \RuntimeException( 'field_id is required for is-local-field.' );
				}
				return [ 'is_local' => (bool) acf_is_local_field( $id ) ];
		}

		throw new \RuntimeException( sprintf( 'Unknown action "%s".', $action ) );
	}

	/** @return array<int, string> */
	private function load_paths(): array {
		if ( ! function_exists( 'acf_get_setting' ) ) {
			return [];
		}
		$paths = acf_get_setting( 'load_json' );
		return is_array( $paths ) ? array_values( array_map( 'strval', $paths ) ) : [];
	}

	private function save_path(): string {
		if ( ! function_exists( 'acf_get_setting' ) ) {
			return '';
		}
		return (string) acf_get_setting( 'save_json' );
	}
}
