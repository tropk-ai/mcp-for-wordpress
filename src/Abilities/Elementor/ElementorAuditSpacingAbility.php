<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorAuditSpacingAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-spacing'; }
	protected function meta(): array { return [ 'label' => __( 'Audit container spacing', 'mcp-for-wordpress' ), 'description' => __( 'Reports min/max/avg of padding/margin values across containers — helps spot inconsistent gaps.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$pads = [];
		foreach ( $page->data() as $top ) {
			if ( ! is_array( $top ) || ! isset( $top["elements"] ) ) continue;
			foreach ( $top["elements"] as $section ) {
				if ( ! is_array( $section ) ) continue;
				$p = $section["settings"]["padding"] ?? null;
				if ( is_array( $p ) ) {
					foreach ( [ "top", "right", "bottom", "left" ] as $k ) {
						$v = isset( $p[ $k ] ) ? (int) $p[ $k ] : null;
						if ( null !== $v ) $pads[] = $v;
					}
				}
			}
		}
		$out = $pads ? [ "min" => min( $pads ), "max" => max( $pads ), "avg" => array_sum( $pads ) / count( $pads ), "samples" => count( $pads ) ] : [ "samples" => 0 ];
		return [ "result" => $out ];
	}
}
