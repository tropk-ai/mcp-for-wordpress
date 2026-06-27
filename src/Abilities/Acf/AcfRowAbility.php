<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Acf\AcfRuntime;

/**
 * Row-level operations on ACF repeater and flexible_content fields. Lets
 * the model add/update/delete a single row without round-tripping the
 * entire array through update-value (faster + safer for big repeaters).
 */
final class AcfRowAbility implements Ability {

	public function slug(): string {
		return 'acf-row';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Manage ACF repeater / flexible_content rows', 'mcp-for-wordpress' ),
			'description'         => __( 'Add, update or delete a single row on a repeater or flexible_content field. row indices are 1-based (ACF convention). For flexible_content rows, include "acf_fc_layout" in `row` set to the layout name.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'action', 'target', 'selector' ],
				'properties'           => [
					'action'   => [
						'type' => 'string',
						'enum' => [ 'add-row', 'update-row', 'delete-row', 'count-rows', 'add-sub-row', 'update-sub-row', 'delete-sub-row' ],
					],
					'target'   => [
						'type'        => 'string',
						'description' => 'ACF target id (numeric post ID, or "user_<id>" / "term_<id>" / "options" / etc.).',
					],
					'selector' => [
						'type'        => 'string',
						'description' => 'Field name (top-level for add-row/update-row/delete-row/count-rows; relative sub-path for *-sub-row, e.g. "address_repeater_0_phones").',
					],
					'row'      => [
						'type'                 => 'object',
						'description'          => 'Row data — a flat object of {sub_field_name: value}. For flexible_content rows, include "acf_fc_layout": "<layout_name>". Required for add-row, update-row, add-sub-row, update-sub-row.',
						'additionalProperties' => true,
					],
					'index'    => [
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => '1-based row index. Required for update-row, delete-row, update-sub-row, delete-sub-row.',
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
		$target = (string) ( $input['target'] ?? '' );
		if ( ctype_digit( $target ) ) {
			return current_user_can( 'edit_post', (int) $target );
		}
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function execute( array $input ): array {
		AcfRuntime::require_active();
		$action   = (string) ( $input['action'] ?? '' );
		$target   = (string) ( $input['target'] ?? '' );
		$selector = (string) ( $input['selector'] ?? '' );
		$row      = (array) ( $input['row'] ?? [] );
		$index    = isset( $input['index'] ) ? (int) $input['index'] : 0;

		if ( '' === $selector ) {
			throw new \RuntimeException( 'selector is required.' );
		}

		switch ( $action ) {
			case 'add-row':
				if ( empty( $row ) ) {
					throw new \RuntimeException( 'row is required for add-row.' );
				}
				$new_index = add_row( $selector, $row, $target );
				return [ 'added' => (bool) $new_index, 'index' => (int) $new_index ];

			case 'update-row':
				if ( $index < 1 || empty( $row ) ) {
					throw new \RuntimeException( 'index (>=1) and row are required for update-row.' );
				}
				return [ 'updated' => (bool) update_row( $selector, $index, $row, $target ) ];

			case 'delete-row':
				if ( $index < 1 ) {
					throw new \RuntimeException( 'index (>=1) is required for delete-row.' );
				}
				return [ 'deleted' => (bool) delete_row( $selector, $index, $target ) ];

			case 'count-rows':
				$raw = get_field( $selector, $target, false );
				return [ 'count' => is_array( $raw ) ? count( $raw ) : 0 ];

			case 'add-sub-row':
				if ( empty( $row ) ) {
					throw new \RuntimeException( 'row is required for add-sub-row.' );
				}
				$new_index = add_sub_row( $selector, $row, $target );
				return [ 'added' => (bool) $new_index, 'index' => (int) $new_index ];

			case 'update-sub-row':
				if ( $index < 1 || empty( $row ) ) {
					throw new \RuntimeException( 'index (>=1) and row are required for update-sub-row.' );
				}
				return [ 'updated' => (bool) update_sub_row( $selector, $index, $row, $target ) ];

			case 'delete-sub-row':
				if ( $index < 1 ) {
					throw new \RuntimeException( 'index (>=1) is required for delete-sub-row.' );
				}
				return [ 'deleted' => (bool) delete_sub_row( $selector, $index, $target ) ];
		}

		throw new \RuntimeException( sprintf( 'Unknown action "%s".', $action ) );
	}
}
