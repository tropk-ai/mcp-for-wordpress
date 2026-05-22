<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Content;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ContentCreateCategoryAbility extends AbstractAbility {
	public function slug(): string { return 'content-create-category'; }
	protected function meta(): array { return [ 'label' => __( 'Create a category', 'mcp-for-wordpress' ), 'description' => __( 'Creates a new "category" taxonomy term. Optional parent, description, slug.', 'mcp-for-wordpress' ) ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'name' ], 'properties' => [ 'name' => [ 'type' => 'string', 'minLength' => 1 ], 'slug' => [ 'type' => 'string' ], 'description' => [ 'type' => 'string' ], 'parent' => [ 'type' => 'integer', 'minimum' => 0 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'created' => [ 'type' => 'boolean' ], 'term_id' => [ 'type' => [ 'integer', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_categories' ); }
	public function execute( array $input = [] ): array {
		$res = wp_insert_term( (string) $input['name'], 'category', [
			'slug' => (string) ( $input['slug'] ?? '' ), 'description' => (string) ( $input['description'] ?? '' ), 'parent' => (int) ( $input['parent'] ?? 0 ),
		] );
		if ( is_wp_error( $res ) ) throw new \RuntimeException( $res->get_error_message() );
		return [ 'created' => true, 'term_id' => (int) $res['term_id'] ];
	}
}
