<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorDuplicateTemplateAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-duplicate-template'; }
	protected function meta(): array { return [ 'label' => __( 'Duplicate an Elementor template', 'mcp-for-wordpress' ), 'description' => __( 'Creates a draft copy of an elementor_library item, carrying over all Elementor meta and taxonomy terms.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'id' ], 'properties' => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ], 'title' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'duplicated' => [ 'type' => 'boolean' ], 'id' => [ 'type' => 'integer' ], 'original_id' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['id'];
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'elementor_library' !== $post->post_type ) throw new \RuntimeException( 'Template not found.' );
		$title = (string) ( $input['title'] ?? ( 'Copy of ' . $post->post_title ) );
		$new = wp_insert_post( [ 'post_type' => 'elementor_library', 'post_title' => $title, 'post_status' => 'draft' ], true );
		if ( is_wp_error( $new ) ) throw new \RuntimeException( $new->get_error_message() );
		$new_id = (int) $new;
		foreach ( [ '_elementor_template_type', '_elementor_template_sub_type', '_elementor_edit_mode', '_elementor_data', '_elementor_page_settings', '_elementor_conditions', '_elementor_popup_display_settings', '_elementor_version' ] as $k ) {
			$v = get_post_meta( $id, $k, true );
			if ( '' !== $v && null !== $v && [] !== $v ) update_post_meta( $new_id, $k, $v );
		}
		$terms = wp_get_object_terms( $id, 'elementor_library_type', [ 'fields' => 'slugs' ] );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) wp_set_object_terms( $new_id, $terms, 'elementor_library_type' );
		return [ 'duplicated' => true, 'id' => $new_id, 'original_id' => $id, 'title' => $title, 'edit' => admin_url( 'post.php?post=' . $new_id . '&action=elementor' ) ];
	}
}
