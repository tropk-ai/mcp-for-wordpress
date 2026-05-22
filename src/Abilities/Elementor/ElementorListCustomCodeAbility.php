<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorListCustomCodeAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-custom-code'; }
	protected function meta(): array { return [
		'label'       => __( 'List Elementor Custom Code snippets', 'mcp-for-wordpress' ),
		'description' => __( 'Lists Elementor Pro Custom Code snippets (elementor_snippet CPT).', 'mcp-for-wordpress' ),
		'readonly'    => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [
			'status'       => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'private', 'trash', 'any' ], 'default' => 'publish' ],
			'limit'        => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ],
			'offset'       => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0 ],
			'include_code' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'snippets' => [ 'type' => 'array' ], 'total' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		if ( ! post_type_exists( 'elementor_snippet' ) ) {
			throw new \RuntimeException( 'Elementor Pro Custom Code is not available.' );
		}
		$status = (string) ( $input['status'] ?? 'publish' );
		$limit  = max( 1, min( 200, (int) ( $input['limit'] ?? 50 ) ) );
		$offset = max( 0, (int) ( $input['offset'] ?? 0 ) );
		$q = new \WP_Query( [
			'post_type'      => 'elementor_snippet',
			'post_status'    => 'any' === $status ? [ 'publish', 'draft', 'private', 'trash' ] : $status,
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );
		$out = [];
		foreach ( $q->posts as $p ) {
			$row = [
				'id'       => (int) $p->ID,
				'title'    => (string) $p->post_title,
				'status'   => (string) $p->post_status,
				'location' => (string) get_post_meta( $p->ID, '_elementor_location', true ),
				'priority' => (int) get_post_meta( $p->ID, '_elementor_priority', true ),
				'date'     => (string) $p->post_date,
			];
			if ( ! empty( $input['include_code'] ) ) {
				$row['code'] = (string) ( get_post_meta( $p->ID, '_elementor_code', true ) ?: $p->post_content );
			}
			$out[] = $row;
		}
		return [ 'snippets' => $out, 'total' => count( $out ) ];
	}
}
