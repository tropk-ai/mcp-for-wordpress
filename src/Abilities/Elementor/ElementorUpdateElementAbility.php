<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorUpdateElementAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-update-element'; }
	protected function meta(): array { return [
		'label' => __( 'Update an Elementor element (replace by ID)', 'mcp-for-wordpress' ),
		'description' => __( 'Replaces a specific element (container or widget) in the Elementor tree with the supplied node. Guards against type/widgetType drift unless force_replace=true.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'element_id', 'element_data' ],
		'properties'           => [
			'post_id'       => [ 'type' => 'integer', 'minimum' => 1 ],
			'element_id'    => [ 'type' => 'string', 'minLength' => 1 ],
			'element_data'  => [ 'type' => 'object' ],
			'force_replace' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'updated' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id           = (int) $input['post_id'];
		$element_id   = (string) $input['element_id'];
		$element_data = (array) $input['element_data'];
		if ( ( $element_data['id'] ?? null ) !== $element_id ) {
			throw new \RuntimeException( 'Element data "id" must match the target element_id.' );
		}
		if ( empty( $element_data['elType'] ) || ! is_string( $element_data['elType'] ) ) {
			throw new \RuntimeException( 'Element data must include a string "elType".' );
		}
		$page = ElementorPage::load( $id );
		$data = $page->data();
		$original = null;
		$find = function ( array $nodes ) use ( &$find, $element_id, &$original ): bool {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) continue;
				if ( ( $n['id'] ?? '' ) === $element_id ) { $original = $n; return true; }
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) && $find( $n['elements'] ) ) return true;
			}
			return false;
		};
		$find( $data );
		if ( ! is_array( $original ) ) {
			throw new \RuntimeException( sprintf( 'Element "%s" not found.', $element_id ) );
		}
		$force = ! empty( $input['force_replace'] );
		if ( ! $force ) {
			if ( ( $original['elType'] ?? null ) !== $element_data['elType'] ) {
				throw new \RuntimeException( 'Refusing to replace element with different elType without force_replace=true.' );
			}
			if ( 'widget' === ( $original['elType'] ?? '' ) && ( $original['widgetType'] ?? null ) !== ( $element_data['widgetType'] ?? null ) ) {
				throw new \RuntimeException( 'Refusing to replace widget with different widgetType without force_replace=true.' );
			}
			$origKids = isset( $original['elements'] ) && is_array( $original['elements'] ) ? $original['elements'] : [];
			$newKids  = isset( $element_data['elements'] ) && is_array( $element_data['elements'] ) ? $element_data['elements'] : [];
			if ( ! empty( $origKids ) && empty( $newKids ) ) {
				throw new \RuntimeException( 'Refusing to replace populated container with empty children without force_replace=true.' );
			}
		}
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-update-element' );
		$replace = function ( array &$nodes ) use ( &$replace, $element_id, $element_data ): bool {
			foreach ( $nodes as $i => &$n ) {
				if ( ! is_array( $n ) ) continue;
				if ( ( $n['id'] ?? '' ) === $element_id ) { $nodes[ $i ] = $element_data; return true; }
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) && $replace( $n['elements'] ) ) return true;
			}
			return false;
		};
		$replace( $data );
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		update_post_meta( $id, '_elementor_data', wp_slash( (string) $json ) );
		ElementorPage::load( $id )->flush_css();
		return [ 'updated' => true, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
