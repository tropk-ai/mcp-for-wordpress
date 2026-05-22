<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Taxonomies;

use Tropk\Mcp\Abilities\AbstractAbility;

final class TaxonomiesAssociateAbility extends AbstractAbility {
	public function slug(): string { return 'taxonomies-associate-with-post-type'; }
	protected function meta(): array { return [
		'label' => __( 'Associate a taxonomy with a post type', 'mcp-for-wordpress' ),
		'description' => __( 'Registers the relationship between an existing taxonomy and an existing post type at runtime. Use this to attach categories or tags to a CPT.', 'mcp-for-wordpress' ),
		'destructive' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'taxonomy', 'post_type' ],
		'properties'           => [
			'taxonomy'  => [ 'type' => 'string' ],
			'post_type' => [ 'type' => 'string' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'associated' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$tax = (string) $input['taxonomy'];
		$pt  = (string) $input['post_type'];
		if ( ! taxonomy_exists( $tax ) ) {
			throw new \RuntimeException( sprintf( 'Taxonomy %s is not registered.', $tax ) );
		}
		if ( ! post_type_exists( $pt ) ) {
			throw new \RuntimeException( sprintf( 'Post type %s is not registered.', $pt ) );
		}
		$ok = (bool) register_taxonomy_for_object_type( $tax, $pt );
		return [ 'associated' => $ok ];
	}
}
