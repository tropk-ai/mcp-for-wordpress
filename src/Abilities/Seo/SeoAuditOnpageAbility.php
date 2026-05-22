<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Seo;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Seo\PageAuditor;

final class SeoAuditOnpageAbility implements Ability {

	public function slug(): string {
		return 'seo-audit-onpage';
	}

	public function definition(): array {
		return [
			'label'               => __( 'On-page SEO audit', 'mcp-for-wordpress' ),
			'description'         => __( 'Fetches a URL and parses the rendered HTML to return a structured on-page snapshot: meta, headings, images, links, JSON-LD, plus issues[] and a derived score.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'url' ],
				'properties'           => [
					'url'     => [ 'type' => 'string', 'format' => 'uri', 'minLength' => 1 ],
					'timeout' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 60, 'default' => 15 ],
				],
			],
			'output_schema'       => [
				'$schema'    => 'https://json-schema.org/draft/2020-12/schema',
				'type'       => 'object',
				'properties' => [
					'url'         => [ 'type' => 'string' ],
					'status'      => [ 'type' => 'integer' ],
					'content_type' => [ 'type' => 'string' ],
					'meta'        => [ 'type' => 'object' ],
					'headings'    => [ 'type' => 'object' ],
					'images'      => [ 'type' => 'object' ],
					'links'       => [ 'type' => 'object' ],
					'jsonld'      => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
					'text_stats'  => [ 'type' => 'object' ],
					'issues'      => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
					'score'       => [ 'type' => 'integer' ],
				],
				'required'   => [ 'url', 'issues', 'score' ],
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

	public function execute( array $input ): array {
		$url     = (string) $input['url'];
		$timeout = (int) ( $input['timeout'] ?? 15 );
		return ( new PageAuditor() )->audit( $url, $timeout );
	}
}
