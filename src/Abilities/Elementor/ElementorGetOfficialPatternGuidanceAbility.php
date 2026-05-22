<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorGetOfficialPatternGuidanceAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-official-pattern-guidance'; }
	protected function meta(): array { return [
		'label'       => __( 'Get Elementor official pattern guidance', 'mcp-for-wordpress' ),
		'description' => __( 'Returns the curated Elementor.com guidance catalog (policy + layout + widget pointers) so design audits stay grounded in Elementor docs.', 'mcp-for-wordpress' ),
		'readonly'    => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [
			'topic' => [ 'type' => 'string', 'enum' => [ 'all', 'layout', 'widgets', 'policy' ], 'default' => 'all' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'topic'    => [ 'type' => 'string' ],
		'guidance' => [ 'type' => 'object' ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$topic = (string) ( $input['topic'] ?? 'all' );
		$catalog = self::catalog();
		switch ( $topic ) {
			case 'layout':
				$guidance = [ 'policy' => $catalog['policy'], 'layout' => $catalog['layout'] ];
				break;
			case 'widgets':
				$guidance = [ 'policy' => $catalog['policy'], 'widgets' => $catalog['widgets'] ];
				break;
			case 'policy':
				$guidance = [ 'policy' => $catalog['policy'] ];
				break;
			default:
				$guidance = $catalog;
				break;
		}
		return [ 'topic' => $topic, 'guidance' => $guidance ];
	}
	private static function catalog(): array {
		return [
			'policy' => [
				'pattern_source_of_truth'      => 'official_elementor_docs_first',
				'implementation_fallback_only' => 'site_local_payloads_after_pattern_choice',
				'description'                  => 'Use Elementor.com as the canonical source for widget/layout pattern recommendations. Inspect local Elementor payloads only after the official pattern choice is clear, and only to satisfy serialization or implementation details.',
			],
			'layout' => [
				'grid_for_symmetric_columns' => [
					'label' => 'Grid for equal symmetric columns',
					'url'   => 'https://elementor.com/help/create-a-grid-container/',
				],
				'grid_vs_flex' => [
					'label' => 'Grid vs Flex layout options',
					'url'   => 'https://elementor.com/help/grid-container-layout-options/',
				],
			],
			'widgets' => [
				'accordion'      => [ 'label' => 'Accordion widget', 'url' => 'https://elementor.com/help/accordion-widget/' ],
				'tabs'           => [ 'label' => 'Tabs widget with nested containers', 'url' => 'https://elementor.com/help/tabs-with-nested-containers/' ],
				'call_to_action' => [ 'label' => 'Call to Action widget', 'url' => 'https://elementor.com/help/call-to-action-widget/' ],
				'icon_list'      => [ 'label' => 'Icon List widget', 'url' => 'https://elementor.com/help/icon-list-widget/' ],
			],
		];
	}
}
