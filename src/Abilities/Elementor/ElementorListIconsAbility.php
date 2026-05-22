<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorListIconsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-icons'; }
	protected function meta(): array { return [ 'label' => __( 'List icon widgets', 'mcp-for-wordpress' ), 'description' => __( 'Returns icon and icon-list widgets with their icon classes.', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) ;
	}
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$out = [];
		foreach ( $page->widgets() as $w ) {
			$t = (string) ( $w["widgetType"] ?? "" );
			if ( ! in_array( $t, [ "icon", "icon-list", "icon-box" ], true ) ) continue;
			$n = $page->find_widget( (string) $w["id"] );
			$out[] = [ "id" => $w["id"], "type" => $t, "icon" => (array) ( $n["settings"]["selected_icon"] ?? $n["settings"]["icon"] ?? [] ) ];
		}
		return [ "result" => [ "icons" => $out ] ];
	}
}
