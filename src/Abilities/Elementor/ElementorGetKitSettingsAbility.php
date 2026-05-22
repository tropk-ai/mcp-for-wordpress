<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorGetKitSettingsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-kit-settings'; }
	protected function meta(): array { return [
		'label'       => __( 'Get Elementor Kit settings', 'mcp-for-wordpress' ),
		'description' => __( 'Returns the active Elementor Kit settings (global colors, typography, layout, etc.).', 'mcp-for-wordpress' ),
		'readonly'    => true,
	]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [
		'kit_id'   => [ 'type' => [ 'integer', 'null' ] ],
		'settings' => [ 'type' => 'object' ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit_id ) {
			throw new \RuntimeException( 'No active Elementor kit found.' );
		}
		$kit = get_post( $kit_id );
		if ( ! $kit instanceof \WP_Post || 'elementor_library' !== $kit->post_type ) {
			throw new \RuntimeException( 'Active kit is invalid.' );
		}
		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		return [ 'kit_id' => $kit_id, 'settings' => is_array( $settings ) ? $settings : [] ];
	}
}
