<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorListGlobalWidgetsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-global-widgets'; }
	protected function meta(): array { return [
		'label'       => __( 'List Elementor global widgets', 'mcp-for-wordpress' ),
		'description' => __( 'Returns all Elementor global widgets (reusable widget instances stored as elementor_library/widget).', 'mcp-for-wordpress' ),
		'readonly'    => true,
	]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'widgets' => [ 'type' => 'array' ], 'total' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$q = new \WP_Query( [
			'post_type'      => 'elementor_library',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'tax_query'      => [ [ 'taxonomy' => 'elementor_library_type', 'field' => 'slug', 'terms' => 'widget' ] ],
		] );
		$out = [];
		foreach ( $q->posts as $w ) {
			$out[] = [
				'id'       => (int) $w->ID,
				'title'    => (string) $w->post_title,
				'date'     => (string) $w->post_date,
				'modified' => (string) $w->post_modified,
			];
		}
		return [ 'widgets' => $out, 'total' => count( $out ) ];
	}
}
