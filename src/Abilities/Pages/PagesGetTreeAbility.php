<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Pages;
use Tropk\Mcp\Abilities\AbstractAbility;
final class PagesGetTreeAbility extends AbstractAbility {
	public function slug(): string { return 'pages-get-tree'; }
	protected function meta(): array { return [ 'label' => __( 'Get pages hierarchy tree', 'mcp-for-wordpress' ), 'description' => __( 'Returns a nested tree of pages with their children.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'tree' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_pages' ); }
	public function execute( array $input = [] ): array {
		$pages = get_pages( [ 'sort_column' => 'menu_order, post_title' ] );
		$by_parent = [];
		foreach ( $pages as $p ) $by_parent[ (int) $p->post_parent ][] = $p;
		$build = function ( int $parent ) use ( &$build, $by_parent ) {
			$out = [];
			foreach ( $by_parent[ $parent ] ?? [] as $p ) {
				$out[] = [ 'id' => (int) $p->ID, 'title' => (string) $p->post_title, 'status' => (string) $p->post_status, 'children' => $build( (int) $p->ID ) ];
			}
			return $out;
		};
		return [ 'tree' => $build( 0 ) ];
	}
}
