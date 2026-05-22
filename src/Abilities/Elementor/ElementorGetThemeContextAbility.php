<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorGetThemeContextAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-theme-context'; }
	protected function meta(): array { return [
		'label'       => __( 'Get Elementor theme + site context', 'mcp-for-wordpress' ),
		'description' => __( 'Summarizes active theme, Elementor version, active kit, and viewport breakpoints so design work starts from real site context.', 'mcp-for-wordpress' ),
		'readonly'    => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [
			'include_viewports' => [ 'type' => 'boolean', 'default' => true ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'context'        => [ 'type' => 'object' ],
		'source_policy'  => [ 'type' => 'object' ],
		'guidance_basis' => [ 'type' => 'object' ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$theme  = wp_get_theme();
		$kit_id = (int) get_option( 'elementor_active_kit' );
		$kit    = $kit_id > 0 ? get_post( $kit_id ) : null;
		$context = [
			'theme' => [
				'name'           => (string) $theme->get( 'Name' ),
				'stylesheet'     => (string) $theme->get_stylesheet(),
				'template'       => (string) $theme->get_template(),
				'version'        => (string) $theme->get( 'Version' ),
				'is_block_theme' => function_exists( 'wp_is_block_theme' ) ? (bool) wp_is_block_theme() : false,
			],
			'elementor' => [
				'version'    => defined( 'ELEMENTOR_VERSION' ) ? (string) ELEMENTOR_VERSION : '',
				'active_kit' => [
					'id'     => $kit_id,
					'title'  => $kit instanceof \WP_Post ? (string) $kit->post_title : '',
					'status' => $kit instanceof \WP_Post ? (string) $kit->post_status : '',
				],
			],
		];
		if ( ! array_key_exists( 'include_viewports', $input ) || ! empty( $input['include_viewports'] ) ) {
			$context['elementor']['viewport_options'] = [
				'elementor_viewport_lg' => (string) get_option( 'elementor_viewport_lg', '' ),
				'elementor_viewport_md' => (string) get_option( 'elementor_viewport_md', '' ),
			];
		}
		return [
			'context'        => $context,
			'source_policy'  => [
				'pattern_source_of_truth'      => 'official_elementor_docs_first',
				'implementation_fallback_only' => 'site_local_payloads_after_pattern_choice',
				'description'                  => 'Use Elementor.com as the canonical source for widget/layout pattern recommendations. Inspect local Elementor payloads only after the official pattern choice is clear, and only for serialization/implementation details.',
			],
			'guidance_basis' => [
				'official_elementor_topics' => [ 'layout_mechanism_fit', 'native_widget_opportunities' ],
				'plugin_heuristic_topics'   => [ 'composition', 'spacing', 'typography', 'emphasis', 'column_patterns' ],
				'description'               => 'Official Elementor docs drive widget/layout pattern choice. Other audits are plugin heuristics for composition, pacing, and repetition.',
			],
		];
	}
}
