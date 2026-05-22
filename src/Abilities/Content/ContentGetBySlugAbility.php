<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Content;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ContentGetBySlugAbility extends AbstractAbility {
	public function slug(): string { return 'content-get-by-slug'; }
	protected function meta(): array { return [ 'label' => __( 'Get a post by slug', 'mcp-for-wordpress' ), 'description' => __( 'Returns a single post by its slug, optionally scoped to a post type.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'slug' ], 'properties' => [ 'slug' => [ 'type' => 'string' ], 'post_type' => [ 'type' => 'string', 'default' => 'post' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'found' => [ 'type' => 'boolean' ], 'post_id' => [ 'type' => [ 'integer', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$p = get_page_by_path( (string) $input['slug'], OBJECT, (string) ( $input['post_type'] ?? 'post' ) );
		if ( ! $p instanceof \WP_Post ) return [ 'found' => false, 'post_id' => null ];
		return [ 'found' => true, 'post_id' => (int) $p->ID, 'title' => (string) $p->post_title, 'status' => (string) $p->post_status ];
	}
}
