<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorAuditTypographyAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-typography'; }
	protected function meta(): array { return [ 'label' => __( 'Audit typography consistency', 'mcp-for-wordpress' ), 'description' => __( 'Counts heading widgets per font-family/weight on the page to surface mismatched typography.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$fonts = [];
		foreach ( $page->widgets() as $w ) {
			if ( "heading" !== ( $w["widgetType"] ?? "" ) ) continue;
			$node = $page->find_widget( (string) $w["id"] );
			$family = (string) ( $node["settings"]["typography_font_family"] ?? "" );
			$weight = (string) ( $node["settings"]["typography_font_weight"] ?? "" );
			$key = $family . " " . $weight;
			$fonts[ $key ] = ( $fonts[ $key ] ?? 0 ) + 1;
		}
		return [ "result" => [ "headings_by_typography" => $fonts, "distinct_typography_count" => count( $fonts ) ] ];
	}
}
