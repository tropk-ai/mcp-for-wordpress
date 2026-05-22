<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorUpdateKitSettingsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-update-kit-settings'; }
	protected function meta(): array { return [
		'label'       => __( 'Update Elementor Kit settings', 'mcp-for-wordpress' ),
		'description' => __( 'Merges or replaces the active Elementor Kit settings. Use for global colors, typography, layout, etc.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'settings' ],
		'properties'           => [
			'settings' => [ 'type' => 'object' ],
			'replace'  => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'kit_id'   => [ 'type' => 'integer' ],
		'settings' => [ 'type' => 'object' ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_posts' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$settings = (array) ( $input['settings'] ?? [] );
		if ( empty( $settings ) ) {
			throw new \RuntimeException( 'settings is required.' );
		}
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit_id || ! current_user_can( 'edit_post', $kit_id ) ) {
			throw new \RuntimeException( 'Active Elementor kit unavailable or not editable.' );
		}
		$existing = get_post_meta( $kit_id, '_elementor_page_settings', true );
		$existing = is_array( $existing ) ? $existing : [];
		$final    = ! empty( $input['replace'] ) ? $settings : array_merge( $existing, $settings );
		update_post_meta( $kit_id, '_elementor_page_settings', $final );
		if ( class_exists( '\\Elementor\\Plugin' ) ) {
			$el = \Elementor\Plugin::$instance ?? null;
			if ( $el && isset( $el->files_manager ) && method_exists( $el->files_manager, 'clear_cache' ) ) {
				$el->files_manager->clear_cache();
			}
		}
		return [ 'kit_id' => $kit_id, 'settings' => $final ];
	}
}
