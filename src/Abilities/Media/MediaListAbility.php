<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Media;

use Tropk\Mcp\Abilities\AbstractAbility;

final class MediaListAbility extends AbstractAbility {
	public function slug(): string { return 'media-list'; }
	protected function meta(): array { return [
		'label' => __( 'List media attachments', 'mcp-for-wordpress' ),
		'description' => __( 'Lists attachments from the media library with optional MIME and search filters.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [
			'mime'    => [ 'type' => 'string' ],
			'search'  => [ 'type' => 'string' ],
			'limit'   => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
			'offset'  => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'items' => [ 'type' => 'array' ], 'pageInfo' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'upload_files' ); }
	public function execute( array $input = [] ): array {
		$args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => (int) ( $input['limit'] ?? 20 ),
			'offset'         => (int) ( $input['offset'] ?? 0 ),
		];
		if ( ! empty( $input['mime'] ) ) {
			$args['post_mime_type'] = (string) $input['mime'];
		}
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = (string) $input['search'];
		}
		$q     = new \WP_Query( $args );
		$items = [];
		foreach ( $q->posts as $p ) {
			$items[] = [
				'id'    => (int) $p->ID,
				'title' => (string) $p->post_title,
				'url'   => (string) wp_get_attachment_url( $p->ID ),
				'mime'  => (string) $p->post_mime_type,
				'date'  => (string) $p->post_date_gmt,
			];
		}
		return [
			'items'    => $items,
			'pageInfo' => [ 'total' => (int) $q->found_posts, 'limit' => $args['posts_per_page'], 'offset' => $args['offset'] ],
		];
	}
}
