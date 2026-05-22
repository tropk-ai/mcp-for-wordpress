<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorCountAtomicWidgetsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-count-atomic-widgets'; }
	protected function meta(): array { return [ 'label' => __( 'Count atomic (V4) widgets', 'mcp-for-wordpress' ), 'description' => __( 'Counts how many atomic Editor V4 widgets the page uses. Useful for compatibility audits before importing into a V3-only Elementor build.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$atomic = 0; $classic = 0;
		foreach ( $page->widgets() as $w ) {
			if ( ! empty( $w["atomic"] ) ) $atomic++; else $classic++;
		}
		return [ "post_id" => (int) $input["post_id"], "result" => [ "atomic_widget_count" => $atomic, "classic_widget_count" => $classic ] ];
	}
}
