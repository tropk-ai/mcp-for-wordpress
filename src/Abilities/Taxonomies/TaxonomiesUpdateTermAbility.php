<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Taxonomies;
use Tropk\Mcp\Abilities\AbstractAbility;
final class TaxonomiesUpdateTermAbility extends AbstractAbility {
	public function slug(): string { return 'taxonomies-update-term'; }
	protected function meta(): array { return [ 'label' => __( 'Update a taxonomy term', 'mcp-for-wordpress' ), 'description' => __( 'Renames or re-parents a term.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'taxonomy', 'term_id' ], 'properties' => [ 'taxonomy' => [ 'type' => 'string' ], 'term_id' => [ 'type' => 'integer' ], 'name' => [ 'type' => 'string' ], 'slug' => [ 'type' => 'string' ], 'description' => [ 'type' => 'string' ], 'parent' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_categories' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$args = array_intersect_key( $input, array_flip( [ 'name', 'slug', 'description', 'parent' ] ) );
		$res  = wp_update_term( (int) $input['term_id'], (string) $input['taxonomy'], $args );
		if ( is_wp_error( $res ) ) throw new \RuntimeException( $res->get_error_message() );
		return [ 'updated' => true ];
	}
}
