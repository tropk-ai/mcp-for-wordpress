<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Pages;
use Tropk\Mcp\Abilities\AbstractAbility;
final class PagesListAbility extends AbstractAbility {
	public function slug(): string { return 'pages-list'; }
	protected function meta(): array { return [ 'label' => __( 'List pages', 'mcp-for-wordpress' ), 'description' => __( 'Lists pages with hierarchy depth and template.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => [ 'status' => [ 'type' => 'string' ], 'limit' => [ 'type' => 'integer', 'default' => 50 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'pages' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_pages' ); }
	public function execute( array $input = [] ): array {
		$q = new \WP_Query( [ 'post_type' => 'page', 'posts_per_page' => (int) ( $input['limit'] ?? 50 ), 'post_status' => $input['status'] ?? 'any' ] );
		$out = [];
		foreach ( $q->posts as $p ) {
			$out[] = [ 'id' => (int) $p->ID, 'title' => (string) $p->post_title, 'status' => (string) $p->post_status, 'parent' => (int) $p->post_parent, 'order' => (int) $p->menu_order, 'template' => (string) get_post_meta( $p->ID, '_wp_page_template', true ), 'permalink' => (string) get_permalink( $p ) ];
		}
		return [ 'pages' => $out ];
	}
}
