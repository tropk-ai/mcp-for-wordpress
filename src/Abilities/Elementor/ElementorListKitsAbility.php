<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorListKitsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-kits'; }
	protected function meta(): array { return [
		'label'       => __( 'List Elementor Kits', 'mcp-for-wordpress' ),
		'description' => __( 'Lists all Elementor Site Kits with the active kit flagged.', 'mcp-for-wordpress' ),
		'readonly'    => true,
	]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'kits' => [ 'type' => 'array' ], 'active_kit_id' => [ 'type' => [ 'integer', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$active = (int) get_option( 'elementor_active_kit' );
		$query  = new \WP_Query( [
			'post_type'      => 'elementor_library',
			'post_status'    => [ 'publish', 'draft', 'private' ],
			'posts_per_page' => 200,
			'meta_query'     => [ [ 'key' => '_elementor_template_type', 'value' => 'kit' ] ],
		] );
		$kits = [];
		foreach ( $query->posts as $p ) {
			$kits[] = [
				'id'        => (int) $p->ID,
				'title'     => (string) $p->post_title,
				'status'    => (string) $p->post_status,
				'modified'  => (string) $p->post_modified_gmt,
				'is_active' => (int) $p->ID === $active,
			];
		}
		return [ 'kits' => $kits, 'active_kit_id' => $active > 0 ? $active : null ];
	}
}
