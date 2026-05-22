<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorAuditTextHierarchyAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-text-hierarchy'; }
	protected function meta(): array { return [ 'label' => __( 'Audit text hierarchy', 'mcp-for-wordpress' ), 'description' => __( 'Counts heading widgets by level (h1..h6) across the page and flags duplicate-H1 issues.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$counts = [ "h1"=>0, "h2"=>0, "h3"=>0, "h4"=>0, "h5"=>0, "h6"=>0, "other"=>0 ];
		foreach ( $page->widgets() as $w ) {
			if ( "heading" !== ( $w["widgetType"] ?? "" ) ) continue;
			$node = $page->find_widget( $w["id"] );
			$level = strtolower( (string) ( $node["settings"]["header_size"] ?? "h2" ) );
			$counts[ array_key_exists( $level, $counts ) ? $level : "other" ]++;
		}
		return [ "post_id" => (int) $input["post_id"], "result" => [ "counts" => $counts, "duplicate_h1" => $counts["h1"] > 1, "missing_h1" => 0 === $counts["h1"] ] ];
	}
}
