<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorAuditColorsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-colors'; }
	protected function meta(): array { return [ 'label' => __( 'Audit colour palette consistency', 'mcp-for-wordpress' ), 'description' => __( 'Returns every distinct background_color / text_color / heading_color setting found on the page.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$colors = [];
		foreach ( $page->widgets() as $w ) {
			$node = $page->find_widget( (string) $w["id"] );
			foreach ( [ "title_color", "text_color", "background_color", "header_color", "color" ] as $k ) {
				if ( isset( $node["settings"][ $k ] ) && is_string( $node["settings"][ $k ] ) && "" !== $node["settings"][ $k ] ) {
					$colors[ $node["settings"][ $k ] ] = ( $colors[ $node["settings"][ $k ] ] ?? 0 ) + 1;
				}
			}
		}
		arsort( $colors );
		return [ "result" => [ "colors" => $colors, "distinct" => count( $colors ) ] ];
	}
}
