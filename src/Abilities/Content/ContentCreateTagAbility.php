<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Content;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ContentCreateTagAbility extends AbstractAbility {
	public function slug(): string { return 'content-create-tag'; }
	protected function meta(): array { return [ 'label' => __( 'Create a tag', 'mcp-for-wordpress' ), 'description' => __( 'Creates a new "post_tag" taxonomy term.', 'mcp-for-wordpress' ) ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'name' ], 'properties' => [ 'name' => [ 'type' => 'string' ], 'slug' => [ 'type' => 'string' ], 'description' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'created' => [ 'type' => 'boolean' ], 'term_id' => [ 'type' => [ 'integer', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_categories' ); }
	public function execute( array $input = [] ): array {
		$res = wp_insert_term( (string) $input['name'], 'post_tag', [ 'slug' => (string) ( $input['slug'] ?? '' ), 'description' => (string) ( $input['description'] ?? '' ) ] );
		if ( is_wp_error( $res ) ) throw new \RuntimeException( $res->get_error_message() );
		return [ 'created' => true, 'term_id' => (int) $res['term_id'] ];
	}
}
