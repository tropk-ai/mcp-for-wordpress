<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorListCustomCssAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-custom-css'; }
	protected function meta(): array { return [ 'label' => __( 'Find widgets with custom CSS', 'mcp-for-wordpress' ), 'description' => __( 'Returns widgets whose custom_css setting is non-empty (Elementor Pro).', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) ;
	}
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$out = [];
		foreach ( $page->widgets() as $w ) {
			$n = $page->find_widget( (string) $w["id"] );
			$css = (string) ( $n["settings"]["custom_css"] ?? "" );
			if ( "" !== $css ) $out[] = [ "id" => $w["id"], "css_length" => strlen( $css ) ];
		}
		return [ "result" => [ "widgets_with_custom_css" => $out ] ];
	}
}
