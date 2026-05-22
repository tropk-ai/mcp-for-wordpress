<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorListThemeBuilderAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-theme-builder'; }
	protected function meta(): array { return [ 'label' => __( 'List Elementor Theme Builder items', 'mcp-for-wordpress' ), 'description' => __( 'Lists every elementor_library post grouped by template_type (header / footer / single / archive / popup …).', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'items_by_type' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$q = new \WP_Query( [ 'post_type' => 'elementor_library', 'posts_per_page' => 500, 'post_status' => 'any' ] );
		$out = [];
		foreach ( $q->posts as $p ) {
			$type = (string) get_post_meta( $p->ID, '_elementor_template_type', true );
			$out[ $type ][] = [ 'id' => (int) $p->ID, 'title' => (string) $p->post_title, 'status' => (string) $p->post_status ];
		}
		return [ 'items_by_type' => (object) $out ];
	}
}
