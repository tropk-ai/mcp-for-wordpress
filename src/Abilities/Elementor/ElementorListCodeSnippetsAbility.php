<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorListCodeSnippetsAbility extends AbstractAbility {
	private const LABELS = [
		'elementor_head'       => 'head',
		'elementor_body_start' => 'body_start',
		'elementor_body_end'   => 'body_end',
	];
	private const KEYS = [
		'head'       => 'elementor_head',
		'body_start' => 'elementor_body_start',
		'body_end'   => 'elementor_body_end',
	];

	public function slug(): string { return 'elementor-list-code-snippets'; }
	protected function meta(): array { return [
		'label'       => __( 'List Elementor Pro Code Snippets', 'mcp-for-wordpress' ),
		'description' => __( 'Returns Elementor Pro Custom Code snippets with title, location, priority, status, and code.', 'mcp-for-wordpress' ),
		'readonly'    => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [
			'location' => [ 'type' => 'string', 'enum' => [ 'head', 'body_start', 'body_end' ] ],
			'status'   => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'any' ], 'default' => 'any' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'snippets' => [ 'type' => 'array' ], 'count' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		if ( ! post_type_exists( 'elementor_snippet' ) ) {
			throw new \RuntimeException( 'Elementor Pro Custom Code is not available.' );
		}
		$status = (string) ( $input['status'] ?? 'any' );
		$args = [
			'post_type'      => 'elementor_snippet',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_status'    => 'any' === $status ? [ 'publish', 'draft' ] : $status,
		];
		if ( ! empty( $input['location'] ) ) {
			$args['meta_query'] = [ [ 'key' => '_elementor_location', 'value' => self::KEYS[ (string) $input['location'] ] ?? 'elementor_head' ] ];
		}
		$posts = get_posts( $args );
		$out = [];
		foreach ( $posts as $p ) {
			$raw_loc = (string) get_post_meta( $p->ID, '_elementor_location', true );
			$out[] = [
				'id'       => (int) $p->ID,
				'title'    => (string) $p->post_title,
				'location' => self::LABELS[ $raw_loc ] ?? $raw_loc,
				'priority' => (int) ( get_post_meta( $p->ID, '_elementor_priority', true ) ?: 1 ),
				'status'   => (string) $p->post_status,
				'code'     => (string) get_post_meta( $p->ID, '_elementor_code', true ),
			];
		}
		return [ 'snippets' => $out, 'count' => count( $out ) ];
	}
}
