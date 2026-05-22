<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorListPagesWithStatsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-pages-with-stats'; }
	protected function meta(): array { return [ 'label' => __( 'List Elementor pages with widget statistics', 'mcp-for-wordpress' ), 'description' => __( 'Returns every Elementor page plus widget count and atomic/classic split.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => [ 'limit' => [ 'type' => 'integer', 'default' => 50 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'pages' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$q = new \WP_Query( [ 'post_type' => [ 'page', 'post' ], 'posts_per_page' => (int) ( $input['limit'] ?? 50 ), 'meta_key' => '_elementor_edit_mode', 'meta_value' => 'builder' ] );
		$out = [];
		foreach ( $q->posts as $p ) {
			$widgets = ElementorPage::load( (int) $p->ID )->widgets();
			$atomic = 0; $classic = 0;
			foreach ( $widgets as $w ) ! empty( $w['atomic'] ) ? $atomic++ : $classic++;
			$out[] = [ 'id' => (int) $p->ID, 'title' => (string) $p->post_title, 'widgets' => count( $widgets ), 'atomic' => $atomic, 'classic' => $classic ];
		}
		return [ 'pages' => $out ];
	}
}
