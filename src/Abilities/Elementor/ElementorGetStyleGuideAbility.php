<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorGetStyleGuideAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-style-guide'; }
	protected function meta(): array { return [
		'label' => __( 'Get Elementor style guide', 'mcp-for-wordpress' ),
		'description' => __( 'Builds a style-guide summary from the active Elementor kit, including global colors, typography, layout tokens, and optionally the raw kit settings.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [
			'include_raw_settings' => [ 'type' => 'boolean', 'default' => true ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'style_guide' => [ 'type' => 'object' ], 'kit_id' => [ 'type' => [ 'integer', 'null' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			throw new \RuntimeException( 'Elementor is not active.' );
		}
		$el = \Elementor\Plugin::$instance ?? null;
		$kit = $el && isset( $el->kits_manager ) && method_exists( $el->kits_manager, 'get_active_kit' ) ? $el->kits_manager->get_active_kit() : null;
		if ( ! $kit ) {
			throw new \RuntimeException( 'Active Elementor kit not found.' );
		}
		$settings = (array) $kit->get_settings();
		$style_guide = [
			'system_colors'     => is_array( $settings['system_colors']     ?? null ) ? $settings['system_colors']     : [],
			'custom_colors'     => is_array( $settings['custom_colors']     ?? null ) ? $settings['custom_colors']     : [],
			'system_typography' => is_array( $settings['system_typography'] ?? null ) ? $settings['system_typography'] : [],
			'custom_typography' => is_array( $settings['custom_typography'] ?? null ) ? $settings['custom_typography'] : [],
			'layout'            => array_intersect_key( $settings, array_flip( [
				'container_width', 'content_width', 'space_between_widgets',
				'page_title_selector', 'stretched_section_container',
				'viewport_md', 'viewport_lg', 'active_breakpoints',
			] ) ),
		];
		$include_raw = ! array_key_exists( 'include_raw_settings', $input ) || ! empty( $input['include_raw_settings'] );
		if ( $include_raw ) {
			$style_guide['raw_settings'] = $settings;
		}
		$kit_id = is_object( $kit ) && method_exists( $kit, 'get_id' ) ? (int) $kit->get_id() : null;
		return [ 'style_guide' => $style_guide, 'kit_id' => $kit_id ];
	}
}
