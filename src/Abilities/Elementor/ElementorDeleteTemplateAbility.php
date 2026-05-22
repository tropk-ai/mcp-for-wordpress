<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
final class ElementorDeleteTemplateAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-delete-template'; }
	protected function meta(): array { return [ 'label' => __( 'Delete an Elementor template', 'mcp-for-wordpress' ), 'description' => __( 'Trashes (default) or permanently deletes an elementor_library item. Snapshots the post first and removes any theme builder conditions referencing it.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'id' ], 'properties' => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ], 'force' => [ 'type' => 'boolean', 'default' => false ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deleted' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'delete_post', (int) ( $input['id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['id'];
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'elementor_library' !== $post->post_type ) throw new \RuntimeException( 'Template not found.' );
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-delete-template' );
		$template_type = (string) get_post_meta( $id, '_elementor_template_type', true );
		$conditions = get_option( 'elementor_pro_theme_builder_conditions', [] );
		if ( is_array( $conditions ) && isset( $conditions[ $template_type ][ $id ] ) ) {
			unset( $conditions[ $template_type ][ $id ] );
			update_option( 'elementor_pro_theme_builder_conditions', $conditions );
		}
		$force = ! empty( $input['force'] );
		$res = $force ? wp_delete_post( $id, true ) : wp_trash_post( $id );
		if ( ! $res ) throw new \RuntimeException( 'Failed to delete template.' );
		return [ 'deleted' => true, 'forced' => $force, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
