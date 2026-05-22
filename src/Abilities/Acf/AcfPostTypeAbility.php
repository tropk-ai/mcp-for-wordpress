<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Acf\AngieAcfBridge;

final class AcfPostTypeAbility implements Ability {

	public function slug(): string {
		return 'acf-post-type';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Manage ACF custom post types', 'mcp-for-wordpress' ),
			'description'         => __( 'List, create, update or delete custom post types via ACF (requires ACF 6.1+). `config` accepts all standard register_post_type args: labels, supports, hierarchical, public, show_ui, has_archive, taxonomies, capabilities, rewrite, show_in_rest, menu_icon, etc.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'action' ],
				'properties'           => [
					'action'    => [
						'type' => 'string',
						'enum' => [ 'get-post-types', 'get-post-type', 'create-post-type', 'update-post-type', 'delete-post-type' ],
					],
					'post_type' => [
						'type'        => 'string',
						'description' => 'Post type slug like "product". Required for get-post-type, update-post-type, delete-post-type.',
					],
					'config'    => [
						'type'                 => 'object',
						'description'          => 'Post type configuration. Required for create-post-type and update-post-type. Must contain at least `post_type`, `title`, `labels.name`, `labels.singular_name`.',
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
			case 'get-post-types':
				return [ 'post_types' => $bridge->request( 'GET', 'post-types' ) ];

			case 'get-post-type':
				$slug = (string) ( $input['post_type'] ?? '' );
				if ( '' === $slug ) {
					throw new \RuntimeException( 'post_type is required for get-post-type.' );
				}
				return [ 'post_type' => $bridge->request( 'GET', 'post-types/' . rawurlencode( $slug ) ) ];

			case 'create-post-type':
				$config = (array) ( $input['config'] ?? [] );
				if ( empty( $config ) ) {
					throw new \RuntimeException( 'config is required for create-post-type.' );
				}
				return [ 'created' => true, 'result' => $bridge->request( 'POST', 'post-types', $config ) ];

			case 'update-post-type':
				$slug   = (string) ( $input['post_type'] ?? '' );
				$config = (array) ( $input['config'] ?? [] );
				if ( '' === $slug || empty( $config ) ) {
					throw new \RuntimeException( 'post_type and config are required for update-post-type.' );
				}
				return [ 'updated' => true, 'result' => $bridge->request( 'PUT', 'post-types/' . rawurlencode( $slug ), $config ) ];

			case 'delete-post-type':
				$slug = (string) ( $input['post_type'] ?? '' );
				if ( '' === $slug ) {
					throw new \RuntimeException( 'post_type is required for delete-post-type.' );
				}
				return [ 'deleted' => true, 'result' => $bridge->request( 'DELETE', 'post-types/' . rawurlencode( $slug ) ) ];
		}

		throw new \RuntimeException( sprintf( 'Unknown action "%s".', $action ) );
	}
}
