<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorUpdateDataAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-update-data'; }
	protected function meta(): array { return [
		'label' => __( 'Update Elementor data', 'mcp-for-wordpress' ),
		'description' => __( 'Replaces the entire _elementor_data tree for a post. Refuses destructive shrinks unless force_replace=true. Snapshots first and flushes CSS afterwards.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'data' ],
		'properties'           => [
			'post_id'       => [ 'type' => 'integer', 'minimum' => 1 ],
			'data'          => [ 'type' => 'array' ],
			'force_replace' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'updated' => [ 'type' => 'boolean' ], 'unchanged' => [ 'type' => 'boolean' ],
		'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		if ( ! get_post( $id ) instanceof \WP_Post ) {
			throw new \RuntimeException( sprintf( 'Post %d not found.', $id ) );
		}
		$new_data      = (array) $input['data'];
		$force_replace = (bool) ( $input['force_replace'] ?? false );
		$existing_raw  = get_post_meta( $id, '_elementor_data', true );
		$existing_tree = is_string( $existing_raw ) && '' !== $existing_raw ? json_decode( $existing_raw, true ) : [];
		if ( ! is_array( $existing_tree ) ) {
			throw new \RuntimeException( 'Failed to parse existing Elementor data.' );
		}
		if ( ! $force_replace ) {
			$old = count( $existing_tree );
			$new = count( $new_data );
			if ( $old > 0 && 0 === $new ) {
				throw new \RuntimeException( 'Refusing to replace populated Elementor document with empty data without force_replace=true.' );
			}
			if ( $old > 1 && $new < (int) ceil( $old / 2 ) ) {
				throw new \RuntimeException( 'Refusing to drastically shrink Elementor document structure without force_replace=true.' );
			}
		}
		$json = wp_json_encode( $new_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			throw new \RuntimeException( 'Failed to encode Elementor data.' );
		}
		if ( is_string( $existing_raw ) && $existing_raw === $json ) {
			return [ 'updated' => false, 'unchanged' => true, 'snapshot_id' => null ];
		}
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-update-data' );
		update_post_meta( $id, '_elementor_data', wp_slash( $json ) );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		ElementorPage::load( $id )->flush_css();
		return [ 'updated' => true, 'unchanged' => false, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
