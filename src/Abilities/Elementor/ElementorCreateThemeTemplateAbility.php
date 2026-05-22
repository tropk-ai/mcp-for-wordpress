<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorCreateThemeTemplateAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-create-theme-template'; }
	protected function meta(): array { return [ 'label' => __( 'Create an Elementor Pro theme template', 'mcp-for-wordpress' ), 'description' => __( 'Creates a new theme builder template (header, footer, single, archive, search-results, error-404, loop-item) in the Elementor library.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'title', 'template_type' ], 'properties' => [ 'title' => [ 'type' => 'string', 'minLength' => 1 ], 'template_type' => [ 'type' => 'string', 'enum' => [ 'header', 'footer', 'single', 'single-post', 'single-page', 'archive', 'search-results', 'error-404', 'loop-item' ] ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'created' => [ 'type' => 'boolean' ], 'id' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$title = (string) $input['title'];
		$type = (string) $input['template_type'];
		$id = wp_insert_post( [
			'post_type'   => 'elementor_library',
			'post_title'  => $title,
			'post_status' => 'publish',
			'meta_input'  => [ '_elementor_edit_mode' => 'builder', '_elementor_template_type' => $type ],
		], true );
		if ( is_wp_error( $id ) ) throw new \RuntimeException( $id->get_error_message() );
		$post_id = (int) $id;
		wp_set_object_terms( $post_id, $type, 'elementor_library_type' );
		update_post_meta( $post_id, '_elementor_data', wp_slash( '[]' ) );
		return [ 'created' => true, 'id' => $post_id, 'title' => $title, 'template_type' => $type, 'edit' => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ) ];
	}
}
