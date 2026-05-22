<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Acf\AngieAcfBridge;

final class AcfTaxonomyAbility implements Ability {

	public function slug(): string {
		return 'acf-taxonomy';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Manage ACF custom taxonomies', 'mcp-for-wordpress' ),
			'description'         => __( 'List, create, update or delete custom taxonomies via ACF. `config` accepts standard register_taxonomy args plus ACF-specific keys.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'action' ],
				'properties'           => [
					'action'   => [
						'type' => 'string',
						'enum' => [ 'get-taxonomies', 'get-taxonomy', 'create-taxonomy', 'update-taxonomy', 'delete-taxonomy' ],
					],
					'taxonomy' => [
						'type'        => 'string',
						'description' => 'Taxonomy slug like "product_category". Required for get-taxonomy, update-taxonomy, delete-taxonomy.',
					],
					'config'   => [
						'type'                 => 'object',
						'description'          => 'Taxonomy configuration. Required for create-taxonomy and update-taxonomy. Must contain at least `taxonomy`, `label`, `labels.name`, `labels.singular_name`, `object_type[]`.',
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
			case 'get-taxonomies':
				return [ 'taxonomies' => $bridge->request( 'GET', 'taxonomies' ) ];

			case 'get-taxonomy':
				$slug = (string) ( $input['taxonomy'] ?? '' );
				if ( '' === $slug ) {
					throw new \RuntimeException( 'taxonomy is required for get-taxonomy.' );
				}
				return [ 'taxonomy' => $bridge->request( 'GET', 'taxonomies/' . rawurlencode( $slug ) ) ];

			case 'create-taxonomy':
				$config = (array) ( $input['config'] ?? [] );
				if ( empty( $config ) ) {
					throw new \RuntimeException( 'config is required for create-taxonomy.' );
				}
				return [ 'created' => true, 'result' => $bridge->request( 'POST', 'taxonomies', $config ) ];

			case 'update-taxonomy':
				$slug   = (string) ( $input['taxonomy'] ?? '' );
				$config = (array) ( $input['config'] ?? [] );
				if ( '' === $slug || empty( $config ) ) {
					throw new \RuntimeException( 'taxonomy and config are required for update-taxonomy.' );
				}
				return [ 'updated' => true, 'result' => $bridge->request( 'PUT', 'taxonomies/' . rawurlencode( $slug ), $config ) ];

			case 'delete-taxonomy':
				$slug = (string) ( $input['taxonomy'] ?? '' );
				if ( '' === $slug ) {
					throw new \RuntimeException( 'taxonomy is required for delete-taxonomy.' );
				}
				return [ 'deleted' => true, 'result' => $bridge->request( 'DELETE', 'taxonomies/' . rawurlencode( $slug ) ) ];
		}

		throw new \RuntimeException( sprintf( 'Unknown action "%s".', $action ) );
	}
}
