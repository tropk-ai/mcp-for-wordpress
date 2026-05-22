<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorListConditionsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-theme-builder-conditions'; }
	protected function meta(): array { return [ 'label' => __( 'List Theme Builder conditions', 'mcp-for-wordpress' ), 'description' => __( 'Returns Elementor Pro Theme Builder display conditions for each library item (header, footer, single, archive). Read-only.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'items' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$q = new \WP_Query( [ 'post_type' => 'elementor_library', 'posts_per_page' => 200, 'meta_query' => [ [ 'key' => '_elementor_conditions', 'compare' => 'EXISTS' ] ] ] );
		$out = [];
		foreach ( $q->posts as $p ) {
			$out[] = [
				'id' => (int) $p->ID,
				'title' => (string) $p->post_title,
				'type'  => (string) get_post_meta( $p->ID, '_elementor_template_type', true ),
				'conditions' => maybe_unserialize( (string) get_post_meta( $p->ID, '_elementor_conditions', true ) ),
			];
		}
		return [ 'items' => $out ];
	}
}
