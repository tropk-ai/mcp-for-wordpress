<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
final class ElementorDeletePageContentAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-delete-page-content'; }
	protected function meta(): array { return [ 'label' => __( 'Clear Elementor content from a page', 'mcp-for-wordpress' ), 'description' => __( 'Resets _elementor_data to an empty array, keeping the post itself intact. Snapshots the post first.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'cleared' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post ) throw new \RuntimeException( 'Post not found.' );
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-delete-page-content' );
		update_post_meta( $id, '_elementor_data', wp_slash( '[]' ) );
		delete_post_meta( $id, '_elementor_css' );
		if ( class_exists( '\\Elementor\\Plugin' ) ) {
			$el = \Elementor\Plugin::$instance ?? null;
			if ( $el && isset( $el->files_manager ) && method_exists( $el->files_manager, 'clear_cache' ) ) {
				$el->files_manager->clear_cache();
			}
		}
		return [ 'cleared' => true, 'post_id' => $id, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
