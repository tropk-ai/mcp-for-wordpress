<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorMoveElementAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-move-element'; }
	protected function meta(): array { return [
		'label' => __( 'Move an Elementor element', 'mcp-for-wordpress' ),
		'description' => __( 'Moves an existing element to a new parent and/or position. Pass an empty new_parent_id for top-level.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'element_id' ],
		'properties'           => [
			'post_id'       => [ 'type' => 'integer', 'minimum' => 1 ],
			'element_id'    => [ 'type' => 'string', 'minLength' => 1 ],
			'new_parent_id' => [ 'type' => 'string' ],
			'position'      => [ 'type' => 'integer', 'default' => -1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'moved' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id        = (int) $input['post_id'];
		$elem_id   = (string) $input['element_id'];
		$parent_id = isset( $input['new_parent_id'] ) ? (string) $input['new_parent_id'] : '';
		$position  = isset( $input['position'] ) ? (int) $input['position'] : -1;
		$page      = ElementorPage::load( $id );
		$data      = $page->data();
		// Extract element.
		$extracted = null;
		$remove = function ( array &$nodes ) use ( &$remove, $elem_id, &$extracted ): bool {
			foreach ( $nodes as $i => &$n ) {
				if ( ! is_array( $n ) ) continue;
				if ( ( $n['id'] ?? '' ) === $elem_id ) { $extracted = $n; array_splice( $nodes, $i, 1 ); return true; }
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) && $remove( $n['elements'] ) ) return true;
			}
			return false;
		};
		if ( ! $remove( $data ) || ! is_array( $extracted ) ) {
			throw new \RuntimeException( sprintf( 'Element "%s" not found.', $elem_id ) );
		}
		// Guard against moving inside itself.
		$contains = function ( array $node, string $target ) use ( &$contains ): bool {
			if ( ( $node['id'] ?? '' ) === $target ) return true;
			foreach ( (array) ( $node['elements'] ?? [] ) as $k ) {
				if ( is_array( $k ) && $contains( $k, $target ) ) return true;
			}
			return false;
		};
		if ( '' !== $parent_id && $contains( $extracted, $parent_id ) ) {
			throw new \RuntimeException( 'Cannot move an element inside itself or its descendants.' );
		}
		// Insert.
		$insert = function ( array &$nodes, string $pid ) use ( &$insert, $extracted, $position ): bool {
			if ( '' === $pid ) {
				if ( $position < 0 || $position >= count( $nodes ) ) $nodes[] = $extracted;
				else array_splice( $nodes, $position, 0, [ $extracted ] );
				return true;
			}
			foreach ( $nodes as &$n ) {
				if ( ! is_array( $n ) ) continue;
				if ( ( $n['id'] ?? '' ) === $pid ) {
					if ( ! isset( $n['elements'] ) || ! is_array( $n['elements'] ) ) $n['elements'] = [];
					if ( $position < 0 || $position >= count( $n['elements'] ) ) $n['elements'][] = $extracted;
					else array_splice( $n['elements'], $position, 0, [ $extracted ] );
					return true;
				}
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) && $insert( $n['elements'], $pid ) ) return true;
			}
			return false;
		};
		if ( ! $insert( $data, $parent_id ) ) {
			throw new \RuntimeException( 'Destination parent not found.' );
		}
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-move-element' );
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		update_post_meta( $id, '_elementor_data', wp_slash( (string) $json ) );
		ElementorPage::load( $id )->flush_css();
		return [ 'moved' => true, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
