<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorGetTemplateAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-template'; }
	protected function meta(): array { return [ 'label' => __( 'Get a full Elementor template', 'mcp-for-wordpress' ), 'description' => __( 'Retrieves a saved elementor_library entry: title, type, status, _elementor_data, page settings, conditions, popup settings, edit URL.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'id' ], 'properties' => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'id' => [ 'type' => 'integer' ], 'title' => [ 'type' => 'string' ], 'type' => [ 'type' => 'string' ], 'status' => [ 'type' => 'string' ], 'data' => [ 'type' => 'array' ], 'page_settings' => [ 'type' => [ 'object', 'array' ] ], 'conditions' => [ 'type' => 'array' ], 'popup_settings' => [ 'type' => [ 'object', 'array' ] ], 'link' => [ 'type' => 'string' ], 'edit' => [ 'type' => 'string' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['id'];
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'elementor_library' !== $post->post_type ) {
			throw new \RuntimeException( sprintf( 'Template %d not found.', $id ) );
		}
		$page_settings = get_post_meta( $id, '_elementor_page_settings', true );
		$conditions = get_post_meta( $id, '_elementor_conditions', true );
		$popup_settings = get_post_meta( $id, '_elementor_popup_display_settings', true );
		return [
			'id'             => $id,
			'title'          => (string) $post->post_title,
			'type'           => (string) get_post_meta( $id, '_elementor_template_type', true ),
			'sub_type'       => (string) get_post_meta( $id, '_elementor_template_sub_type', true ),
			'status'         => (string) $post->post_status,
			'data'           => ElementorPage::load( $id )->data(),
			'page_settings'  => is_array( $page_settings ) ? $page_settings : [],
			'conditions'     => is_array( $conditions ) ? $conditions : [],
			'popup_settings' => is_array( $popup_settings ) ? $popup_settings : [],
			'link'           => (string) get_permalink( $id ),
			'edit'           => admin_url( 'post.php?post=' . $id . '&action=elementor' ),
		];
	}
}
