<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorGetGlobalFontsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-global-fonts'; }
	protected function meta(): array { return [ 'label' => __( 'Get Elementor global fonts', 'mcp-for-wordpress' ), 'description' => __( "Returns the active Elementor Kit's system + custom typography.", 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'system_typography' => [ 'type' => 'array' ], 'custom_typography' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit_id ) return [ 'system_typography' => [], 'custom_typography' => [] ];
		$settings = json_decode( (string) get_post_meta( $kit_id, '_elementor_page_settings', true ), true );
		return [
			'system_typography' => (array) ( $settings['system_typography'] ?? [] ),
			'custom_typography' => (array) ( $settings['custom_typography'] ?? [] ),
		];
	}
}
