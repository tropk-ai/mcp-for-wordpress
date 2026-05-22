<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorGetGlobalColorsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-global-colors'; }
	protected function meta(): array { return [ 'label' => __( 'Get Elementor global colors', 'mcp-for-wordpress' ), 'description' => __( "Returns the active Elementor Kit's system + custom colors.", 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'system_colors' => [ 'type' => 'array' ], 'custom_colors' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit_id ) return [ 'system_colors' => [], 'custom_colors' => [] ];
		$settings = json_decode( (string) get_post_meta( $kit_id, '_elementor_page_settings', true ), true );
		return [
			'system_colors' => (array) ( $settings['system_colors'] ?? [] ),
			'custom_colors' => (array) ( $settings['custom_colors'] ?? [] ),
		];
	}
}
