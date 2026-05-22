<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Taxonomies;
use Tropk\Mcp\Abilities\AbstractAbility;
final class TaxonomiesDeleteTermAbility extends AbstractAbility {
	public function slug(): string { return 'taxonomies-delete-term'; }
	protected function meta(): array { return [ 'label' => __( 'Delete a taxonomy term', 'mcp-for-wordpress' ), 'description' => __( 'Removes a term from a taxonomy. Posts attached to the term lose the association.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'taxonomy', 'term_id' ], 'properties' => [ 'taxonomy' => [ 'type' => 'string' ], 'term_id' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deleted' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_categories' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$ok = wp_delete_term( (int) $input['term_id'], (string) $input['taxonomy'] );
		if ( is_wp_error( $ok ) ) throw new \RuntimeException( $ok->get_error_message() );
		return [ 'deleted' => (bool) $ok ];
	}
}
