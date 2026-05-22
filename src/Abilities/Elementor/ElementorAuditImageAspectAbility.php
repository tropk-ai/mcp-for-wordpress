<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorAuditImageAspectAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-image-aspect'; }
	protected function meta(): array { return [ 'label' => __( 'Audit image aspect-ratio consistency', 'mcp-for-wordpress' ), 'description' => __( 'Flags image widgets where the attached image dimensions differ wildly across the page.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$ratios = [];
		foreach ( $page->widgets() as $w ) {
			$type = (string) ( $w["widgetType"] ?? "" );
			if ( ! in_array( $type, [ "image", "image-box" ], true ) ) continue;
			$node = $page->find_widget( (string) $w["id"] );
			$id = isset( $node["settings"]["image"]["id"] ) ? (int) $node["settings"]["image"]["id"] : 0;
			if ( ! $id ) continue;
			$meta = wp_get_attachment_metadata( $id );
			if ( is_array( $meta ) && ! empty( $meta["height"] ) ) $ratios[] = round( $meta["width"] / $meta["height"], 2 );
		}
		return [ "result" => [ "image_aspect_ratios" => $ratios, "distinct" => count( array_unique( $ratios ) ) ] ];
	}
}
