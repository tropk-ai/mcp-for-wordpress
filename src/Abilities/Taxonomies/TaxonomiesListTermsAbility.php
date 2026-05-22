<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Taxonomies;
use Tropk\Mcp\Abilities\AbstractAbility;
final class TaxonomiesListTermsAbility extends AbstractAbility {
	public function slug(): string { return 'taxonomies-list-terms'; }
	protected function meta(): array { return [ 'label' => __( 'List terms in a taxonomy', 'mcp-for-wordpress' ), 'description' => __( 'Returns terms with parent / count / slug.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'taxonomy' ], 'properties' => [ 'taxonomy' => [ 'type' => 'string' ], 'limit' => [ 'type' => 'integer', 'default' => 100 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'terms' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$terms = get_terms( [ 'taxonomy' => (string) $input['taxonomy'], 'number' => (int) ( $input['limit'] ?? 100 ), 'hide_empty' => false ] );
		if ( is_wp_error( $terms ) ) throw new \RuntimeException( $terms->get_error_message() );
		$out = [];
		foreach ( (array) $terms as $t ) {
			$out[] = [ 'id' => (int) $t->term_id, 'name' => (string) $t->name, 'slug' => (string) $t->slug, 'parent' => (int) $t->parent, 'count' => (int) $t->count ];
		}
		return [ 'terms' => $out ];
	}
}
