<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorDuplicateElementAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-duplicate-element'; }
	protected function meta(): array { return [
		'label' => __( 'Duplicate an Elementor element', 'mcp-for-wordpress' ),
		'description' => __( 'Duplicates an Elementor element subtree with fresh IDs and inserts the copy beside the original (or under a new parent).', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'element_id' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'element_id' => [ 'type' => 'string', 'minLength' => 1 ],
			'parent_id'  => [ 'type' => 'string' ],
			'position'   => [ 'type' => 'integer', 'default' => -1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'duplicated' => [ 'type' => 'boolean' ], 'new_element_id' => [ 'type' => 'string' ],
		'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id      = (int) $input['post_id'];
		$elem_id = (string) $input['element_id'];
		$page    = ElementorPage::load( $id );
		$data    = $page->data();
		$source  = null;
		$parent_path = null;
		$index_in_parent = -1;
		$find = function ( array &$nodes ) use ( &$find, $elem_id, &$source, &$parent_path, &$index_in_parent ): bool {
			foreach ( $nodes as $i => &$n ) {
				if ( ! is_array( $n ) ) continue;
				if ( ( $n['id'] ?? '' ) === $elem_id ) { $source = $n; $parent_path = &$nodes; $index_in_parent = $i; return true; }
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) && $find( $n['elements'] ) ) return true;
			}
			return false;
		};
		$find( $data );
		if ( ! is_array( $source ) ) {
			throw new \RuntimeException( sprintf( 'Element "%s" not found.', $elem_id ) );
		}
		// Reassign IDs recursively.
		$new_id_gen = function (): string {
			try { return bin2hex( random_bytes( 4 ) ); }
			catch ( \Throwable $e ) { return substr( md5( uniqid( '', true ) ), 0, 8 ); }
		};
		$reid = function ( array $node ) use ( &$reid, $new_id_gen ): array {
			$node['id'] = $new_id_gen();
			if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
				foreach ( $node['elements'] as $k => $child ) {
					if ( is_array( $child ) ) $node['elements'][ $k ] = $reid( $child );
				}
			}
			return $node;
		};
		$dup = $reid( $source );
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-duplicate-element' );
		$parent_id = isset( $input['parent_id'] ) ? (string) $input['parent_id'] : '';
		$position  = isset( $input['position'] ) ? (int) $input['position'] : -1;
		if ( '' === $parent_id ) {
			// Insert directly after source (or at position).
			if ( $position < 0 ) {
				array_splice( $parent_path, $index_in_parent + 1, 0, [ $dup ] );
			} else {
				array_splice( $parent_path, $position, 0, [ $dup ] );
			}
		} else {
			$inserted = false;
			$insert = function ( array &$nodes ) use ( &$insert, $parent_id, $dup, $position, &$inserted ): void {
				foreach ( $nodes as &$n ) {
					if ( ! is_array( $n ) ) continue;
					if ( ( $n['id'] ?? '' ) === $parent_id ) {
						if ( ! isset( $n['elements'] ) || ! is_array( $n['elements'] ) ) $n['elements'] = [];
						if ( $position < 0 || $position >= count( $n['elements'] ) ) $n['elements'][] = $dup;
						else array_splice( $n['elements'], $position, 0, [ $dup ] );
						$inserted = true;
						return;
					}
					if ( isset( $n['elements'] ) && is_array( $n['elements'] ) ) $insert( $n['elements'] );
					if ( $inserted ) return;
				}
			};
			$insert( $data );
			if ( ! $inserted ) {
				throw new \RuntimeException( 'Destination parent not found.' );
			}
		}
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		update_post_meta( $id, '_elementor_data', wp_slash( (string) $json ) );
		ElementorPage::load( $id )->flush_css();
		return [ 'duplicated' => true, 'new_element_id' => (string) $dup['id'], 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
