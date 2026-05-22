<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorRestoreTemplateAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-restore-template'; }
	protected function meta(): array { return [ 'label' => __( 'Restore a trashed Elementor template', 'mcp-for-wordpress' ), 'description' => __( 'Brings a trashed elementor_library item back to publish or draft status.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'id' ], 'properties' => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ], 'status' => [ 'type' => 'string', 'enum' => [ 'publish', 'draft' ], 'default' => 'draft' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'restored' => [ 'type' => 'boolean' ], 'status' => [ 'type' => 'string' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['id'];
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'elementor_library' !== $post->post_type ) throw new \RuntimeException( 'Template not found.' );
		if ( 'trash' !== $post->post_status ) throw new \RuntimeException( 'Template is not in trash.' );
		$new_status = (string) ( $input['status'] ?? 'draft' );
		$res = wp_update_post( [ 'ID' => $id, 'post_status' => $new_status ], true );
		if ( is_wp_error( $res ) ) throw new \RuntimeException( $res->get_error_message() );
		return [ 'restored' => true, 'id' => $id, 'status' => $new_status, 'title' => (string) $post->post_title ];
	}
}
