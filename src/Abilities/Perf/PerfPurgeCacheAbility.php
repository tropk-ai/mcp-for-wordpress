<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Perf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Cache\CachePurger;

final class PerfPurgeCacheAbility implements Ability {

	public function slug(): string {
		return 'perf-purge-cache';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Purge cache', 'mcp-for-wordpress' ),
			'description'         => __( 'Detects installed cache plugins (WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, Cache Enabler, Autoptimize) and triggers their documented purge APIs. Scope can be a single post or the entire site.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'scope' ],
				'properties'           => [
					'scope'    => [ 'type' => 'string', 'enum' => [ 'post', 'all' ] ],
					'post_id'  => [ 'type' => 'integer', 'minimum' => 1, 'description' => 'Required when scope=post.' ],
					'provider' => [
						'type'    => 'string',
						'enum'    => array_merge( [ 'auto' ], CachePurger::PROVIDERS ),
						'default' => 'auto',
					],
					'dry_run'  => [ 'type' => 'boolean', 'default' => false ],
				],
			],
			'output_schema'       => [
				'$schema'    => 'https://json-schema.org/draft/2020-12/schema',
				'type'       => 'object',
				'properties' => [
					'scope'    => [ 'type' => 'string' ],
					'dry_run'  => [ 'type' => 'boolean' ],
					'detected' => [ 'type' => 'object' ],
					'results'  => [ 'type' => 'object' ],
					'post_id'  => [ 'type' => [ 'integer', 'null' ] ],
				],
				'required'   => [ 'scope', 'dry_run', 'detected' ],
			],
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations' => [
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				],
				'show_in_rest' => true,
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		$scope = (string) ( $input['scope'] ?? '' );
		if ( 'post' === $scope && isset( $input['post_id'] ) ) {
			return current_user_can( 'edit_post', (int) $input['post_id'] );
		}
		return current_user_can( 'manage_options' );
	}

	public function execute( array $input ): array {
		$scope    = (string) $input['scope'];
		$dry_run  = (bool) ( $input['dry_run'] ?? false );
		$provider = isset( $input['provider'] ) ? (string) $input['provider'] : 'auto';

		$purger   = new CachePurger();
		$detected = $purger->detect();

		if ( $dry_run ) {
			return [
				'scope'    => $scope,
				'dry_run'  => true,
				'detected' => $detected,
				'results'  => new \stdClass(),
				'post_id'  => isset( $input['post_id'] ) ? (int) $input['post_id'] : null,
			];
		}

		if ( 'post' === $scope ) {
			if ( empty( $input['post_id'] ) ) {
				throw new \RuntimeException( 'post_id is required when scope=post.' );
			}
			$result = $purger->purge_post( (int) $input['post_id'], 'auto' === $provider ? null : $provider );
			return [
				'scope'    => 'post',
				'dry_run'  => false,
				'detected' => $detected,
				'results'  => $result['results'],
				'post_id'  => (int) $input['post_id'],
			];
		}

		$result = $purger->purge_all( 'auto' === $provider ? null : $provider );
		return [
			'scope'    => 'all',
			'dry_run'  => false,
			'detected' => $detected,
			'results'  => $result['results'],
			'post_id'  => null,
		];
	}
}
