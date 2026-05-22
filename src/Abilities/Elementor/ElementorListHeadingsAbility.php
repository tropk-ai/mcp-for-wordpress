<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorListHeadingsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-headings'; }
	protected function meta(): array { return [ 'label' => __( 'List Elementor heading widgets', 'mcp-for-wordpress' ), 'description' => __( 'Returns every heading widget with text, level and id.', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) ;
	}
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$out = [];
		foreach ( $page->widgets() as $w ) {
			if ( "heading" !== ( $w["widgetType"] ?? "" ) ) continue;
			$n = $page->find_widget( (string) $w["id"] );
			$out[] = [ "id" => $w["id"], "text" => (string) ( $n["settings"]["title"] ?? "" ), "level" => strtolower( (string) ( $n["settings"]["header_size"] ?? "h2" ) ) ];
		}
		return [ "result" => [ "headings" => $out ] ];
	}
}
