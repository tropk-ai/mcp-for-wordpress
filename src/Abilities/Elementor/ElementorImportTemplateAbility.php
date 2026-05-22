<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorImportTemplateAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-import-template'; }
	protected function meta(): array { return [ 'label' => __( 'Import an Elementor template', 'mcp-for-wordpress' ), 'description' => __( 'Creates a new elementor_library entry from an export-template payload ({version, title, type, content, page_settings}).', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'data' ], 'properties' => [ 'data' => [ 'type' => 'object' ], 'title' => [ 'type' => 'string' ], 'status' => [ 'type' => 'string', 'enum' => [ 'publish', 'draft' ], 'default' => 'draft' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'imported' => [ 'type' => 'boolean' ], 'id' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$data = (array) ( $input['data'] ?? [] );
		if ( ! isset( $data['content'] ) ) throw new \RuntimeException( 'Invalid export payload: missing content.' );
		$title = (string) ( $input['title'] ?? ( $data['title'] ?? 'Imported Template' ) );
		$type = (string) ( $data['type'] ?? 'page' );
		$status = (string) ( $input['status'] ?? 'draft' );
		$id = wp_insert_post( [ 'post_type' => 'elementor_library', 'post_title' => $title, 'post_status' => $status ], true );
		if ( is_wp_error( $id ) ) throw new \RuntimeException( $id->get_error_message() );
		$post_id = (int) $id;
		$content = is_array( $data['content'] ) ? $data['content'] : [];
		$regen = function( array &$nodes ) use ( &$regen ) {
			foreach ( $nodes as &$n ) {
				if ( ! is_array( $n ) ) continue;
				$n['id'] = bin2hex( random_bytes( 4 ) );
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) ) $regen( $n['elements'] );
			}
			unset( $n );
		};
		$regen( $content );
		update_post_meta( $post_id, '_elementor_template_type', $type );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_data', wp_slash( (string) wp_json_encode( $content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
		if ( ! empty( $data['page_settings'] ) && is_array( $data['page_settings'] ) ) update_post_meta( $post_id, '_elementor_page_settings', $data['page_settings'] );
		wp_set_object_terms( $post_id, $type, 'elementor_library_type' );
		return [ 'imported' => true, 'id' => $post_id, 'title' => $title, 'type' => $type, 'edit' => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ) ];
	}
}
