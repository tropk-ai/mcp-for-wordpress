<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorReorderElementsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-reorder-elements'; }
	protected function meta(): array { return [
		'label' => __( 'Reorder Elementor elements', 'mcp-for-wordpress' ),
		'description' => __( 'Reorders the direct children of a container (or top-level elements when no parent is provided) by their IDs. Children not listed remain in their original relative order at the tail.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'element_ids' ],
		'properties'           => [
			'post_id'     => [ 'type' => 'integer', 'minimum' => 1 ],
			'parent_id'   => [ 'type' => 'string' ],
			'element_ids' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'minItems' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'reordered' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id        = (int) $input['post_id'];
		$parent_id = isset( $input['parent_id'] ) ? (string) $input['parent_id'] : '';
		$wanted    = array_values( array_map( 'strval', (array) $input['element_ids'] ) );
		$page      = ElementorPage::load( $id );
		$data      = $page->data();
		$reorder_list = function ( array $children ) use ( $wanted ): array {
			$by_id = [];
			foreach ( $children as $c ) {
				if ( is_array( $c ) && isset( $c['id'] ) ) $by_id[ (string) $c['id'] ] = $c;
			}
			$out = [];
			foreach ( $wanted as $wid ) {
				if ( isset( $by_id[ $wid ] ) ) {
					$out[] = $by_id[ $wid ];
					unset( $by_id[ $wid ] );
				}
			}
			foreach ( $children as $c ) {
				if ( ! is_array( $c ) ) continue;
				$cid = (string) ( $c['id'] ?? '' );
				if ( isset( $by_id[ $cid ] ) ) $out[] = $c;
			}
			return $out;
		};
		$done = false;
		if ( '' === $parent_id ) {
			$data = $reorder_list( $data );
			$done = true;
		} else {
			$apply = function ( array &$nodes ) use ( &$apply, $parent_id, $reorder_list, &$done ): void {
				foreach ( $nodes as &$n ) {
					if ( ! is_array( $n ) ) continue;
					if ( ( $n['id'] ?? '' ) === $parent_id && isset( $n['elements'] ) && is_array( $n['elements'] ) ) {
						$n['elements'] = $reorder_list( $n['elements'] );
						$done = true;
						return;
					}
					if ( isset( $n['elements'] ) && is_array( $n['elements'] ) ) $apply( $n['elements'] );
					if ( $done ) return;
				}
			};
			$apply( $data );
		}
		if ( ! $done ) {
			throw new \RuntimeException( 'Parent element not found or has no children.' );
		}
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-reorder-elements' );
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		update_post_meta( $id, '_elementor_data', wp_slash( (string) $json ) );
		ElementorPage::load( $id )->flush_css();
		return [ 'reordered' => true, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
