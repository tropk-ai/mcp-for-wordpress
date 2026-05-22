<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorListIdsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-ids'; }
	protected function meta(): array { return [ 'label' => __( 'List every element ID on a page', 'mcp-for-wordpress' ), 'description' => __( 'Returns the full set of container/section/column/widget IDs.', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) ;
	}
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$ids = [];
		$walk = function( array $nodes ) use ( &$walk, &$ids ) {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) continue;
				$ids[] = [ "id" => (string) ( $n["id"] ?? "" ), "elType" => (string) ( $n["elType"] ?? "" ) ];
				if ( isset( $n["elements"] ) && is_array( $n["elements"] ) ) $walk( $n["elements"] );
			}
		};
		$walk( $page->data() );
		return [ "result" => [ "ids" => $ids, "count" => count( $ids ) ] ];
	}
}
