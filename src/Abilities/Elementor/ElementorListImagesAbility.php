<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorListImagesAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-images'; }
	protected function meta(): array { return [ 'label' => __( 'List Elementor image widgets', 'mcp-for-wordpress' ), 'description' => __( 'Returns every image-bearing widget (image, image-box, gallery) with URL + alt.', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) ;
	}
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$out = [];
		foreach ( $page->widgets() as $w ) {
			$type = (string) ( $w["widgetType"] ?? "" );
			if ( ! in_array( $type, [ "image", "image-box", "image-gallery", "image-carousel" ], true ) ) continue;
			$n = $page->find_widget( (string) $w["id"] );
			$img = $n["settings"]["image"] ?? $n["settings"]["image_box"] ?? null;
			if ( is_array( $img ) ) {
				$out[] = [ "id" => $w["id"], "type" => $type, "url" => (string) ( $img["url"] ?? "" ), "attachment_id" => (int) ( $img["id"] ?? 0 ) ];
			}
		}
		return [ "result" => [ "images" => $out ] ];
	}
}
