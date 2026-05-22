<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorAuditColumnBalanceAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-column-balance'; }
	protected function meta(): array { return [ 'label' => __( 'Audit column balance', 'mcp-for-wordpress' ), 'description' => __( 'Reports columns that are visually unbalanced (very wide vs very narrow siblings) inside the same section.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
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
				$widths = [];
				foreach ( $section["elements"] as $col ) {
					if ( ! is_array( $col ) ) continue;
					$w = (float) ( $col["settings"]["_inline_size"] ?? $col["settings"]["_column_size"] ?? 0 );
					if ( $w > 0 ) $widths[] = $w;
				}
				if ( count( $widths ) > 1 ) {
					$min = min( $widths ); $max = max( $widths );
					if ( $max / max( 1, $min ) > 3 ) {
						$issues[] = [ "section_id" => (string) ( $section["id"] ?? "" ), "widths" => $widths ];
					}
				}
			}
		}
		return [ "post_id" => (int) $input["post_id"], "result" => [ "unbalanced_sections" => $issues ] ];
	}
}
