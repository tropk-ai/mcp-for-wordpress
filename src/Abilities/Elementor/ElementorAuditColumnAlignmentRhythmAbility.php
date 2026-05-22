<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorAuditColumnAlignmentRhythmAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-column-alignment-rhythm'; }
	protected function meta(): array { return [ 'label' => __( 'Audit column alignment rhythm', 'mcp-for-wordpress' ), 'description' => __( 'Flags inconsistent column alignment across sibling columns (some left, some center, some right) within the same row.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
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
				$aligns = [];
				foreach ( $section["elements"] as $col ) {
					$a = (string) ( $col["settings"]["align"] ?? $col["settings"]["content_position"] ?? "" );
					if ( "" !== $a ) $aligns[] = $a;
				}
				if ( count( array_unique( $aligns ) ) > 1 ) {
					$issues[] = [ "section_id" => (string) ( $section["id"] ?? "" ), "aligns" => $aligns ];
				}
			}
		}
		return [ "post_id" => (int) $input["post_id"], "result" => [ "inconsistent_alignment" => $issues ] ];
	}
}
