<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorListContainersAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-containers'; }
	protected function meta(): array { return [ 'label' => __( 'List Elementor containers', 'mcp-for-wordpress' ), 'description' => __( 'Returns every top-level container with id, child count and a 60-char preview of its first heading.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$out = [];
		foreach ( $page->data() as $top ) {
			if ( ! is_array( $top ) ) continue;
			if ( ( $top["elType"] ?? "" ) !== "container" && ( $top["elType"] ?? "" ) !== "section" ) continue;
			$children = isset( $top["elements"] ) && is_array( $top["elements"] ) ? count( $top["elements"] ) : 0;
			$out[] = [ "id" => (string) ( $top["id"] ?? "" ), "elType" => (string) ( $top["elType"] ?? "" ), "children" => $children, "isInner" => (bool) ( $top["isInner"] ?? false ) ];
		}
		return [ "post_id" => (int) $input["post_id"], "result" => [ "containers" => $out, "count" => count( $out ) ] ];
	}
}
