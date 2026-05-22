<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorCreatePageAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-create-page'; }
	protected function meta(): array { return [ 'label' => __( 'Create an Elementor-enabled page', 'mcp-for-wordpress' ), 'description' => __( 'Creates a new WordPress page/post and flips it into Elementor builder mode with optional initial _elementor_data and page settings.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'title' ], 'properties' => [
		'title'   => [ 'type' => 'string', 'minLength' => 1 ],
		'content' => [ 'type' => 'string' ],
		'status'  => [ 'type' => 'string', 'enum' => [ 'draft', 'publish', 'pending', 'private' ], 'default' => 'draft' ],
		'post_type' => [ 'type' => 'string', 'default' => 'page' ],
		'slug'    => [ 'type' => 'string' ],
		'data'    => [ 'type' => 'array' ],
		'page_settings' => [ 'type' => 'object' ],
	] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'created' => [ 'type' => 'boolean' ], 'id' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		$pt = (string) ( $input['post_type'] ?? 'page' );
		if ( '' === $pt || ! post_type_exists( $pt ) ) return false;
		$pto = get_post_type_object( $pt );
		$cap = $pto && isset( $pto->cap->create_posts ) ? (string) $pto->cap->create_posts : 'edit_posts';
		return current_user_can( $cap ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$pt = (string) ( $input['post_type'] ?? 'page' );
		if ( ! post_type_exists( $pt ) ) throw new \RuntimeException( 'Invalid post_type.' );
		$id = wp_insert_post( [
			'post_title'   => (string) $input['title'],
			'post_content' => isset( $input['content'] ) ? (string) $input['content'] : '',
			'post_status'  => (string) ( $input['status'] ?? 'draft' ),
			'post_type'    => $pt,
			'post_name'    => isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : '',
		], true );
		if ( is_wp_error( $id ) ) throw new \RuntimeException( $id->get_error_message() );
		$post_id = (int) $id;
		$data = isset( $input['data'] ) && is_array( $input['data'] ) ? $input['data'] : [];
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_data', wp_slash( (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
		if ( isset( $input['page_settings'] ) && is_array( $input['page_settings'] ) ) {
			update_post_meta( $post_id, '_elementor_page_settings', $input['page_settings'] );
		}
		return [ 'created' => true, 'id' => $post_id, 'title' => (string) get_the_title( $post_id ), 'permalink' => (string) get_permalink( $post_id ) ];
	}
}
