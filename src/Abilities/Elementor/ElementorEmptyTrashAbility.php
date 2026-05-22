<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorEmptyTrashAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-empty-trash'; }
	protected function meta(): array { return [
		'label'       => __( 'Empty Elementor template trash', 'mcp-for-wordpress' ),
		'description' => __( 'Permanently deletes all trashed Elementor templates (elementor_library). Optionally filter by template type.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [
			'type' => [ 'type' => 'string', 'enum' => [ 'all', 'page', 'section', 'container', 'popup', 'header', 'footer', 'single', 'archive' ], 'default' => 'all' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deleted' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'delete_posts' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$type = (string) ( $input['type'] ?? 'all' );
		$args = [
			'post_type'      => 'elementor_library',
			'post_status'    => 'trash',
			'posts_per_page' => 200,
			'paged'          => 1,
			'fields'         => 'ids',
		];
		if ( 'all' !== $type ) {
			$args['tax_query'] = [ [ 'taxonomy' => 'elementor_library_type', 'field' => 'slug', 'terms' => $type ] ];
		}
		$deleted = 0;
		do {
			$ids = get_posts( $args );
			foreach ( $ids as $pid ) {
				if ( wp_delete_post( (int) $pid, true ) ) $deleted++;
			}
			$args['paged']++;
		} while ( ! empty( $ids ) );
		return [ 'deleted' => $deleted ];
	}
}
