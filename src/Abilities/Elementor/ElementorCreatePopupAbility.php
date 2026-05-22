<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorCreatePopupAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-create-popup'; }
	protected function meta(): array { return [ 'label' => __( 'Create an Elementor Pro popup', 'mcp-for-wordpress' ), 'description' => __( 'Creates a new elementor_library entry of type "popup" with empty content. Use elementor-set-popup-settings to configure triggers/timing/conditions.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'title' ], 'properties' => [ 'title' => [ 'type' => 'string', 'minLength' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'created' => [ 'type' => 'boolean' ], 'id' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$title = (string) $input['title'];
		$id = wp_insert_post( [
			'post_type'   => 'elementor_library',
			'post_title'  => $title,
			'post_status' => 'publish',
			'meta_input'  => [ '_elementor_edit_mode' => 'builder', '_elementor_template_type' => 'popup' ],
		], true );
		if ( is_wp_error( $id ) ) throw new \RuntimeException( $id->get_error_message() );
		$post_id = (int) $id;
		wp_set_object_terms( $post_id, 'popup', 'elementor_library_type' );
		update_post_meta( $post_id, '_elementor_data', wp_slash( '[]' ) );
		return [ 'created' => true, 'id' => $post_id, 'title' => $title, 'edit' => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ) ];
	}
}
