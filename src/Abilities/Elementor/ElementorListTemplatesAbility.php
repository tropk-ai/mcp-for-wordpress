<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorListTemplatesAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-templates'; }
	protected function meta(): array { return [ 'label' => __( 'List Elementor templates', 'mcp-for-wordpress' ), 'description' => __( "Lists saved templates from Elementor's library (page, section, popup, header, footer, single, archive).", 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => [ 'template_type' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'templates' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$args = [
			'post_type'      => 'elementor_library',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => 200,
		];
		if ( ! empty( $input['template_type'] ) ) {
			$args['meta_key'] = '_elementor_template_type';
			$args['meta_value'] = (string) $input['template_type'];
		}
		$q = new \WP_Query( $args );
		$out = [];
		foreach ( $q->posts as $p ) {
			$out[] = [
				'id'   => (int) $p->ID,
				'title' => (string) $p->post_title,
				'type'  => (string) get_post_meta( $p->ID, '_elementor_template_type', true ),
				'status' => (string) $p->post_status,
			];
		}
		return [ 'templates' => $out ];
	}
}
