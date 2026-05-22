<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorListPagesAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-pages'; }
	protected function meta(): array { return [
		'label' => __( 'List Elementor pages', 'mcp-for-wordpress' ),
		'description' => __( 'Returns every post/page whose _elementor_edit_mode meta is "builder".', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties' => [
			'post_type' => [ 'type' => 'string', 'default' => 'page' ],
			'limit'     => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'pages' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$q = new \WP_Query( [
			'post_type'      => (string) ( $input['post_type'] ?? 'page' ),
			'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
			'posts_per_page' => (int) ( $input['limit'] ?? 50 ),
			'meta_key'       => '_elementor_edit_mode',
			'meta_value'     => 'builder',
		] );
		$out = [];
		foreach ( $q->posts as $p ) {
			$out[] = [
				'id'        => (int) $p->ID,
				'title'     => (string) $p->post_title,
				'status'    => (string) $p->post_status,
				'permalink' => (string) get_permalink( $p ),
				'post_type' => (string) $p->post_type,
			];
		}
		return [ 'pages' => $out ];
	}
}
