<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorGetPageCssAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-page-css'; }
	protected function meta(): array { return [ 'label' => __( 'Get cached Elementor page CSS', 'mcp-for-wordpress' ), 'description' => __( 'Returns the pre-generated CSS Elementor stored in _elementor_css (post-level).', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$css = get_post_meta( (int) $input["post_id"], "_elementor_css", true );
		return [ "result" => [ "has_css" => "" !== $css && null !== $css, "length" => is_string( $css ) ? strlen( $css ) : 0 ] ];
	}
}
