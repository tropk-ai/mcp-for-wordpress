<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorDeleteElementAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-delete-element'; }
	protected function meta(): array { return [
		'label' => __( 'Delete an Elementor element', 'mcp-for-wordpress' ),
		'description' => __( 'Deletes a specific element (container or widget) by ID. Refuses to delete top-level or populated elements unless force_delete=true.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'element_id' ],
		'properties'           => [
			'post_id'      => [ 'type' => 'integer', 'minimum' => 1 ],
			'element_id'   => [ 'type' => 'string', 'minLength' => 1 ],
			'force_delete' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'deleted' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id         = (int) $input['post_id'];
		$element_id = (string) $input['element_id'];
		$force      = ! empty( $input['force_delete'] );
		$page       = ElementorPage::load( $id );
		$data       = $page->data();
		$target     = null;
		$depth      = -1;
		$find = function ( array $nodes, int $d ) use ( &$find, $element_id, &$target, &$depth ): bool {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) continue;
				if ( ( $n['id'] ?? '' ) === $element_id ) { $target = $n; $depth = $d; return true; }
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) && $find( $n['elements'], $d + 1 ) ) return true;
			}
			return false;
		};
		$find( $data, 0 );
		if ( null === $target ) {
			throw new \RuntimeException( sprintf( 'Element "%s" not found.', $element_id ) );
		}
		$kids = isset( $target['elements'] ) && is_array( $target['elements'] ) ? $target['elements'] : [];
		if ( ! $force ) {
			if ( 0 === $depth ) {
				throw new \RuntimeException( 'Refusing to delete a top-level element without force_delete=true.' );
			}
			if ( ! empty( $kids ) ) {
				throw new \RuntimeException( 'Refusing to delete a populated element without force_delete=true.' );
			}
		}
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-delete-element' );
		$ok   = $page->delete_widget( $element_id );
		if ( $ok ) $page->save();
		return [ 'deleted' => $ok, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
