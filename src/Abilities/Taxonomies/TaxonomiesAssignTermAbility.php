<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Taxonomies;
use Tropk\Mcp\Abilities\AbstractAbility;
final class TaxonomiesAssignTermAbility extends AbstractAbility {
	public function slug(): string { return 'taxonomies-assign-term'; }
	protected function meta(): array { return [ 'label' => __( 'Assign terms to a post', 'mcp-for-wordpress' ), 'description' => __( 'Attaches one or more terms (by ID or slug) of the given taxonomy to a post. Replaces existing terms unless append=true.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id', 'taxonomy', 'terms' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'taxonomy' => [ 'type' => 'string' ], 'terms' => [ 'type' => 'array' ], 'append' => [ 'type' => 'boolean', 'default' => false ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'assigned' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$res = wp_set_object_terms( (int) $input['post_id'], (array) $input['terms'], (string) $input['taxonomy'], (bool) ( $input['append'] ?? false ) );
		if ( is_wp_error( $res ) ) throw new \RuntimeException( $res->get_error_message() );
		return [ 'assigned' => true ];
	}
}
