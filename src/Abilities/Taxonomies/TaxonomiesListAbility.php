<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Taxonomies;
use Tropk\Mcp\Abilities\AbstractAbility;
final class TaxonomiesListAbility extends AbstractAbility {
	public function slug(): string { return 'taxonomies-list'; }
	protected function meta(): array { return [ 'label' => __( 'List taxonomies', 'mcp-for-wordpress' ), 'description' => __( 'Returns every registered taxonomy with the post types it applies to.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'taxonomies' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$out = [];
		foreach ( get_taxonomies( [], 'objects' ) as $t ) {
			$out[] = [ 'name' => (string) $t->name, 'label' => (string) $t->label, 'hierarchical' => (bool) $t->hierarchical, 'public' => (bool) $t->public, 'object_types' => array_values( (array) $t->object_type ) ];
		}
		return [ 'taxonomies' => $out ];
	}
}
