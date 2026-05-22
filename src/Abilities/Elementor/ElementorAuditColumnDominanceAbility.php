<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorAuditColumnDominanceAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-column-dominance'; }
	protected function meta(): array { return [ 'label' => __( 'Audit column dominance', 'mcp-for-wordpress' ), 'description' => __( 'Detects sections where a single column takes more than 70% of the row width — a common readability anti-pattern.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$issues = [];
		foreach ( $page->data() as $top ) {
			if ( ! is_array( $top ) || ! isset( $top["elements"] ) ) continue;
			foreach ( $top["elements"] as $section ) {
				if ( ! is_array( $section ) || ! isset( $section["elements"] ) ) continue;
				foreach ( $section["elements"] as $col ) {
					$w = (float) ( $col["settings"]["_inline_size"] ?? $col["settings"]["_column_size"] ?? 0 );
					if ( $w > 70 ) $issues[] = [ "section_id" => (string) ( $section["id"] ?? "" ), "column_id" => (string) ( $col["id"] ?? "" ), "width" => $w ];
				}
			}
		}
		return [ "post_id" => (int) $input["post_id"], "result" => [ "dominant_columns" => $issues ] ];
	}
}
