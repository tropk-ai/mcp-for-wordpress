<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorCloneDataAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-clone-data'; }
	protected function meta(): array { return [
		'label' => __( 'Clone Elementor data into another post', 'mcp-for-wordpress' ),
		'description' => __( 'Copies _elementor_data (and optionally _elementor_page_settings) from a source post/template into an existing target post.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'source_id', 'target_id' ],
		'properties'           => [
			'source_id'             => [ 'type' => 'integer', 'minimum' => 1 ],
			'target_id'             => [ 'type' => 'integer', 'minimum' => 1 ],
			'include_page_settings' => [ 'type' => 'boolean', 'default' => true ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'cloned' => [ 'type' => 'boolean' ], 'unchanged' => [ 'type' => 'boolean' ],
		'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		$src = (int) ( $input['source_id'] ?? 0 );
		$tgt = (int) ( $input['target_id'] ?? 0 );
		return current_user_can( 'read_post', $src ) && current_user_can( 'edit_post', $tgt ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$src = (int) $input['source_id'];
		$tgt = (int) $input['target_id'];
		if ( ! get_post( $src ) instanceof \WP_Post || ! get_post( $tgt ) instanceof \WP_Post ) {
			throw new \RuntimeException( 'Source or target post not found.' );
		}
		$src_data = get_post_meta( $src, '_elementor_data', true );
		if ( ! is_string( $src_data ) || '' === $src_data ) {
			throw new \RuntimeException( 'Source post has no Elementor data.' );
		}
		$existing = get_post_meta( $tgt, '_elementor_data', true );
		if ( is_string( $existing ) && $existing === $src_data ) {
			return [ 'cloned' => false, 'unchanged' => true, 'snapshot_id' => null ];
		}
		$snap = ( new SnapshotManager() )->snapshot_post( $tgt, 'elementor-clone-data' );
		update_post_meta( $tgt, '_elementor_data', wp_slash( $src_data ) );
		update_post_meta( $tgt, '_elementor_edit_mode', 'builder' );
		if ( ! empty( $input['include_page_settings'] ) || ! array_key_exists( 'include_page_settings', $input ) ) {
			$ps = get_post_meta( $src, '_elementor_page_settings', true );
			if ( '' !== $ps && null !== $ps ) {
				update_post_meta( $tgt, '_elementor_page_settings', $ps );
			}
		}
		ElementorPage::load( $tgt )->flush_css();
		return [ 'cloned' => true, 'unchanged' => false, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
