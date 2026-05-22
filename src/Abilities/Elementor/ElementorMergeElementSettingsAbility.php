<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorMergeElementSettingsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-merge-element-settings'; }
	protected function meta(): array { return [
		'label' => __( 'Merge Elementor element settings', 'mcp-for-wordpress' ),
		'description' => __( 'Deep-merges a settings object into an existing Elementor element without requiring the full element payload. Supports dry_run to preview the merged settings without writing.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'element_id', 'settings' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'element_id' => [ 'type' => 'string', 'minLength' => 1 ],
			'settings'   => [ 'type' => 'object' ],
			'dry_run'    => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'updated' => [ 'type' => 'boolean' ], 'unchanged' => [ 'type' => 'boolean' ],
		'settings' => [ 'type' => 'object' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['post_id'] ?? 0 );
		if ( ! empty( $input['dry_run'] ) ) return current_user_can( 'edit_post', $id );
		return current_user_can( 'edit_post', $id ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$id       = (int) $input['post_id'];
		$elem_id  = (string) $input['element_id'];
		$incoming = (array) $input['settings'];
		$dry_run  = ! empty( $input['dry_run'] );
		$page     = ElementorPage::load( $id );
		$data     = $page->data();
		$original = null;
		$find = function ( array $nodes ) use ( &$find, $elem_id, &$original ): bool {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) continue;
				if ( ( $n['id'] ?? '' ) === $elem_id ) { $original = $n; return true; }
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) && $find( $n['elements'] ) ) return true;
			}
			return false;
		};
		$find( $data );
		if ( ! is_array( $original ) ) {
			throw new \RuntimeException( sprintf( 'Element "%s" not found.', $elem_id ) );
		}
		$deep_merge = function ( array $a, array $b ) use ( &$deep_merge ): array {
			foreach ( $b as $k => $v ) {
				if ( is_array( $v ) && isset( $a[ $k ] ) && is_array( $a[ $k ] ) ) {
					$a[ $k ] = $deep_merge( $a[ $k ], $v );
				} else {
					$a[ $k ] = $v;
				}
			}
			return $a;
		};
		$existing = is_array( $original['settings'] ?? null ) ? $original['settings'] : [];
		$merged   = $deep_merge( $existing, $incoming );
		if ( $merged === $existing ) {
			return [ 'updated' => false, 'unchanged' => true, 'settings' => $merged, 'snapshot_id' => null ];
		}
		if ( $dry_run ) {
			return [ 'updated' => false, 'unchanged' => false, 'settings' => $merged, 'snapshot_id' => null ];
		}
		$replace = function ( array &$nodes ) use ( &$replace, $elem_id, $merged ): bool {
			foreach ( $nodes as &$n ) {
				if ( ! is_array( $n ) ) continue;
				if ( ( $n['id'] ?? '' ) === $elem_id ) { $n['settings'] = $merged; return true; }
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) && $replace( $n['elements'] ) ) return true;
			}
			return false;
		};
		$replace( $data );
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-merge-element-settings' );
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		update_post_meta( $id, '_elementor_data', wp_slash( (string) $json ) );
		ElementorPage::load( $id )->flush_css();
		return [ 'updated' => true, 'unchanged' => false, 'settings' => $merged, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
