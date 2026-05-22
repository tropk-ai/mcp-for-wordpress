<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorApplyTemplateAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-apply-template'; }
	protected function meta(): array { return [ 'label' => __( 'Apply an Elementor template to a page', 'mcp-for-wordpress' ), 'description' => __( "Inserts the saved template's elements into a target post's _elementor_data with fresh IDs. Pass parent_id to nest under a container, omit to insert at top level. position -1 appends. Snapshots the post first.", 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id', 'template_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ], 'template_id' => [ 'type' => 'integer', 'minimum' => 1 ], 'parent_id' => [ 'type' => 'string' ], 'position' => [ 'type' => 'integer', 'default' => -1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'applied' => [ 'type' => 'boolean' ], 'elements_added' => [ 'type' => 'integer' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$post_id = (int) $input['post_id'];
		$template_id = (int) $input['template_id'];
		$parent_id = (string) ( $input['parent_id'] ?? '' );
		$position = isset( $input['position'] ) ? (int) $input['position'] : -1;
		$tpost = get_post( $template_id );
		if ( ! $tpost instanceof \WP_Post || 'elementor_library' !== $tpost->post_type ) throw new \RuntimeException( 'Template not found.' );
		$template = ElementorPage::load( $template_id )->data();
		if ( empty( $template ) ) throw new \RuntimeException( 'Template has no elements.' );
		$regen = function( array &$nodes ) use ( &$regen ) {
			foreach ( $nodes as &$n ) {
				if ( ! is_array( $n ) ) continue;
				$n['id'] = bin2hex( random_bytes( 4 ) );
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) ) $regen( $n['elements'] );
			}
			unset( $n );
		};
		$regen( $template );
		$snap = ( new SnapshotManager() )->snapshot_post( $post_id, 'elementor-apply-template' );
		$page = ElementorPage::load( $post_id );
		$ref = new \ReflectionClass( $page );
		$prop = $ref->getProperty( 'data' );
		$prop->setAccessible( true );
		$data = (array) $prop->getValue( $page );
		if ( '' !== $parent_id ) {
			$inserted = $this->insert_under( $data, $parent_id, $template, $position );
			if ( ! $inserted ) throw new \RuntimeException( sprintf( 'Parent element "%s" not found.', $parent_id ) );
		} else {
			if ( $position < 0 || $position >= count( $data ) ) {
				$data = array_merge( $data, $template );
			} else {
				array_splice( $data, $position, 0, $template );
			}
		}
		$prop->setValue( $page, $data );
		$page->save();
		return [ 'applied' => true, 'elements_added' => count( $template ), 'snapshot_id' => $snap['snapshot_id'] ];
	}
	private function insert_under( array &$nodes, string $parent_id, array $children, int $position ): bool {
		foreach ( $nodes as &$node ) {
			if ( ! is_array( $node ) ) continue;
			if ( ( $node['id'] ?? '' ) === $parent_id ) {
				if ( ! isset( $node['elements'] ) || ! is_array( $node['elements'] ) ) $node['elements'] = [];
				if ( $position < 0 || $position >= count( $node['elements'] ) ) {
					$node['elements'] = array_merge( $node['elements'], $children );
				} else {
					array_splice( $node['elements'], $position, 0, $children );
				}
				return true;
			}
			if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
				if ( $this->insert_under( $node['elements'], $parent_id, $children, $position ) ) return true;
			}
		}
		unset( $node );
		return false;
	}
}
