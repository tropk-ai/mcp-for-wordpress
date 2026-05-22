<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Seo;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\RankMath\RankMathClient;

final class SeoGetHeadAbility implements Ability {

	public function slug(): string {
		return 'seo-get-head';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Get rendered SEO head (Rank Math)', 'mcp-for-wordpress' ),
			'description'         => __( 'Returns the rendered <head> for a URL via Rank Math getHead. Requires Headless CMS Support to be enabled in Rank Math.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'url' ],
				'properties'           => [
					'url' => [ 'type' => 'string', 'format' => 'uri', 'minLength' => 1 ],
				],
			],
			'output_schema'       => [
				'$schema'    => 'https://json-schema.org/draft/2020-12/schema',
				'type'       => 'object',
				'properties' => [
					'available' => [ 'type' => 'boolean' ],
					'url'       => [ 'type' => 'string' ],
					'head'      => [ 'type' => [ 'string', 'null' ] ],
				],
				'required'   => [ 'available', 'url' ],
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
		return current_user_can( 'edit_posts' );
	}

	public function execute( array $input ): array {
		$url       = (string) $input['url'];
		$available = RankMathClient::is_active() && RankMathClient::headless_support_enabled();

		$head = $available ? ( new RankMathClient() )->get_head( $url ) : null;

		return [
			'available' => $available && null !== $head,
			'url'       => $url,
			'head'      => $head,
		];
	}
}
