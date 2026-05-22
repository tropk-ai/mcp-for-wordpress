<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Content;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ContentBulkCreatePostsAbility extends AbstractAbility {
	public function slug(): string { return 'content-bulk-create-posts'; }
	protected function meta(): array { return [ 'label' => __( 'Bulk-create posts', 'mcp-for-wordpress' ), 'description' => __( 'Creates many posts in a single call. Each item: post_type, title, content, status, slug, meta.', 'mcp-for-wordpress' ) ]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required' => [ 'items' ],
		'properties' => [
			'items' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ], 'maxItems' => 100 ],
			'dry_run' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'created' => [ 'type' => 'integer' ], 'failed' => [ 'type' => 'integer' ], 'ids' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'publish_posts' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$items   = (array) ( $input['items'] ?? [] );
		$dry_run = (bool) ( $input['dry_run'] ?? false );
		if ( $dry_run ) return [ 'created' => 0, 'failed' => 0, 'ids' => [], 'dry_run' => true ];
		$created = 0; $failed = 0; $ids = [];
		foreach ( $items as $i ) {
			if ( ! is_array( $i ) ) { $failed++; continue; }
			$id = wp_insert_post( [
				'post_type'    => (string) ( $i['post_type'] ?? 'post' ),
				'post_title'   => (string) ( $i['title'] ?? '' ),
				'post_content' => (string) ( $i['content'] ?? '' ),
				'post_status'  => (string) ( $i['status'] ?? 'draft' ),
				'post_name'    => isset( $i['slug'] ) ? sanitize_title( (string) $i['slug'] ) : '',
			], true );
			if ( is_wp_error( $id ) ) { $failed++; continue; }
			if ( isset( $i['meta'] ) && is_array( $i['meta'] ) ) {
				foreach ( $i['meta'] as $k => $v ) {
					if ( is_string( $k ) ) update_post_meta( (int) $id, $k, $v );
				}
			}
			$ids[] = (int) $id; $created++;
		}
		return [ 'created' => $created, 'failed' => $failed, 'ids' => $ids ];
	}
}
