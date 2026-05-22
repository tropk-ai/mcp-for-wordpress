<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Pages;
use Tropk\Mcp\Abilities\AbstractAbility;
final class PagesCreateAbility extends AbstractAbility {
	public function slug(): string { return 'pages-create'; }
	protected function meta(): array { return [ 'label' => __( 'Create a page', 'mcp-for-wordpress' ), 'description' => __( 'Creates a new WordPress page with optional template, parent, and meta.', 'mcp-for-wordpress' ) ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'title' ], 'properties' => [ 'title' => [ 'type' => 'string' ], 'content' => [ 'type' => 'string' ], 'status' => [ 'type' => 'string', 'default' => 'draft' ], 'parent' => [ 'type' => 'integer' ], 'template' => [ 'type' => 'string' ], 'menu_order' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'created' => [ 'type' => 'boolean' ], 'page_id' => [ 'type' => [ 'integer', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'publish_pages' ); }
	public function execute( array $input = [] ): array {
		$id = wp_insert_post( [
			'post_type' => 'page',
			'post_title' => (string) $input['title'],
			'post_content' => (string) ( $input['content'] ?? '' ),
			'post_status' => (string) ( $input['status'] ?? 'draft' ),
			'post_parent' => (int) ( $input['parent'] ?? 0 ),
			'menu_order' => (int) ( $input['menu_order'] ?? 0 ),
		], true );
		if ( is_wp_error( $id ) ) throw new \RuntimeException( $id->get_error_message() );
		if ( ! empty( $input['template'] ) ) update_post_meta( (int) $id, '_wp_page_template', (string) $input['template'] );
		return [ 'created' => true, 'page_id' => (int) $id ];
	}
}
