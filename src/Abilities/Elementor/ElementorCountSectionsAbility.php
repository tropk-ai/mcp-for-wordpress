<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorCountSectionsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-count-sections'; }
	protected function meta(): array { return [ 'label' => __( 'Count top-level sections/containers', 'mcp-for-wordpress' ), 'description' => __( 'Returns the number of top-level sections and containers.', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) ;
	}
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$sections = 0; $containers = 0;
		foreach ( $page->data() as $top ) {
			if ( ! is_array( $top ) ) continue;
			if ( "section" === ( $top["elType"] ?? "" ) ) $sections++;
			elseif ( "container" === ( $top["elType"] ?? "" ) ) $containers++;
		}
		return [ "result" => [ "sections" => $sections, "containers" => $containers ] ];
	}
}
