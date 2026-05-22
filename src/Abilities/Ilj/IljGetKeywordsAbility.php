<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Ilj;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Ilj\IljClient;

final class IljGetKeywordsAbility implements Ability {

	public function slug(): string {
		return 'ilj-get-keywords';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Get Internal Link Juicer keywords', 'mcp-for-wordpress' ),
			'description'         => __( 'Returns the ilj_linkdefinition postmeta entries (keywords that ILJ uses to link to this post).', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'post_id' ],
				'properties'           => [
					'post_id' => [ 'type' => 'integer', 'minimum' => 1 ],
				],
			],
			'output_schema'       => [
				'$schema'    => 'https://json-schema.org/draft/2020-12/schema',
				'type'       => 'object',
				'properties' => [
					'post_id'    => [ 'type' => 'integer' ],
					'ilj_active' => [ 'type' => 'boolean' ],
					'keywords'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				],
				'required'   => [ 'post_id', 'ilj_active', 'keywords' ],
			],
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations' => [ 'readonly' => true, 'idempotent' => true ],
				'show_in_rest' => true,
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['post_id'] ?? 0 );
		return $id > 0 && current_user_can( 'edit_post', $id );
	}

	public function execute( array $input ): array {
		$post_id = (int) $input['post_id'];
		return [
			'post_id'    => $post_id,
			'ilj_active' => IljClient::is_active(),
			'keywords'   => ( new IljClient() )->get_keywords( $post_id ),
		];
	}
}
