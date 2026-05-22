<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Ilj;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Ilj\IljClient;

final class IljFindOrphansAbility implements Ability {

	public function slug(): string {
		return 'ilj-find-orphans';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Find orphan posts using ILJ index', 'mcp-for-wordpress' ),
			'description'         => __( 'Returns published posts that do not appear in the ILJ link index (no inbound or outbound internal-link record). Best-effort: relies on introspecting the linkindex columns, so accuracy depends on ILJ schema.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => [
					'post_type' => [ 'type' => 'string', 'default' => 'post' ],
					'limit'     => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 50 ],
				],
			],
			'output_schema'       => [
				'$schema'    => 'https://json-schema.org/draft/2020-12/schema',
				'type'       => 'object',
				'properties' => [
					'ilj_active' => [ 'type' => 'boolean' ],
					'orphans'    => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'post_id' => [ 'type' => 'integer' ],
								'title'   => [ 'type' => 'string' ],
							],
						],
					],
				],
				'required'   => [ 'ilj_active', 'orphans' ],
			],
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations' => [ 'readonly' => true, 'idempotent' => true ],
				'show_in_rest' => true,
			],
		];
	}

	public function authorize(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function execute( array $input = [] ): array {
		$post_type = (string) ( $input['post_type'] ?? 'post' );
		$limit     = (int) ( $input['limit'] ?? 50 );

		return [
			'ilj_active' => IljClient::is_active(),
			'orphans'    => ( new IljClient() )->orphan_posts( $post_type, $limit ),
		];
	}
}
