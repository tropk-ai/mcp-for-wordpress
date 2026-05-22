<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorGetPageSettingsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-page-settings'; }
	protected function meta(): array { return [ 'label' => __( 'Get Elementor page settings', 'mcp-for-wordpress' ), 'description' => __( 'Returns the _elementor_page_settings meta (template, layout, custom CSS, hide title etc).', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'settings' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$raw = get_post_meta( (int) $input['post_id'], '_elementor_page_settings', true );
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : [] );
		return [ 'settings' => is_array( $decoded ) ? $decoded : new \stdClass() ];
	}
}
