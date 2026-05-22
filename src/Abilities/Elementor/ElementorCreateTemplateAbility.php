<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorCreateTemplateAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-create-template'; }
	protected function meta(): array { return [ 'label' => __( 'Create an Elementor template', 'mcp-for-wordpress' ), 'description' => __( 'Creates a new elementor_library entry of the given type (page, section, container, loop-item, header, footer, single, archive, popup) with optional initial _elementor_data, page settings, conditions, and popup display settings.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'title', 'type' ], 'properties' => [
		'title'   => [ 'type' => 'string', 'minLength' => 1 ],
		'type'    => [ 'type' => 'string', 'enum' => [ 'page', 'section', 'container', 'loop-item', 'header', 'footer', 'single', 'archive', 'popup' ] ],
		'status'  => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'private' ], 'default' => 'publish' ],
		'data'    => [ 'type' => 'array' ],
		'page_settings' => [ 'type' => 'object' ],
		'conditions'    => [ 'type' => 'array' ],
		'popup_display' => [ 'type' => 'object' ],
	] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'id' => [ 'type' => 'integer' ], 'edit' => [ 'type' => 'string' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = wp_insert_post( [
			'post_type'   => 'elementor_library',
			'post_title'  => (string) $input['title'],
			'post_status' => (string) ( $input['status'] ?? 'publish' ),
		], true );
		if ( is_wp_error( $id ) ) throw new \RuntimeException( $id->get_error_message() );
		$post_id = (int) $id;
		$type = (string) $input['type'];
		update_post_meta( $post_id, '_elementor_template_type', $type );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		$data = isset( $input['data'] ) && is_array( $input['data'] ) ? $input['data'] : [];
		update_post_meta( $post_id, '_elementor_data', wp_slash( (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
		if ( isset( $input['page_settings'] ) && is_array( $input['page_settings'] ) ) update_post_meta( $post_id, '_elementor_page_settings', $input['page_settings'] );
		if ( isset( $input['conditions'] ) && is_array( $input['conditions'] ) ) update_post_meta( $post_id, '_elementor_conditions', $input['conditions'] );
		if ( 'popup' === $type && isset( $input['popup_display'] ) && is_array( $input['popup_display'] ) ) update_post_meta( $post_id, '_elementor_popup_display_settings', $input['popup_display'] );
		wp_set_object_terms( $post_id, $type, 'elementor_library_type' );
		return [ 'id' => $post_id, 'title' => (string) $input['title'], 'type' => $type, 'edit' => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ) ];
	}
}
