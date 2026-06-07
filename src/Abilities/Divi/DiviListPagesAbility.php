<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Divi;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Divi\DiviPage;

final class DiviListPagesAbility extends AbstractAbility {
	public function slug(): string { return 'divi-list-pages'; }
	protected function meta(): array { return [
		'label'       => __( 'List Divi 5 pages', 'mcp-for-wordpress' ),
		'description' => __( 'Returns a list of posts/pages that use the Divi 5 builder, with title, URL, status and last-modified date.', 'mcp-for-wordpress' ),
		'readonly'    => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties' => [
			'post_type' => [ 'type' => 'string', 'default' => 'page' ],
			'status'    => [ 'type' => 'string', 'default' => 'any' ],
			'per_page'  => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ],
			'page'      => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'total'  => [ 'type' => 'integer' ],
		'pages'  => [ 'type' => 'array' ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_posts' );
	}
	public function execute( array $input = [] ): array {
		$post_type = sanitize_text_field( (string) ( $input['post_type'] ?? 'page' ) );
		$per_page  = (int) ( $input['per_page'] ?? 50 );
		$paged     = (int) ( $input['page'] ?? 1 );
		$status    = sanitize_text_field( (string) ( $input['status'] ?? 'any' ) );

		$query = new \WP_Query( [
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'meta_key'       => '_et_pb_use_builder',
			'meta_value'     => 'on',
			'fields'         => 'ids',
			'no_found_rows'  => false,
		] );

		$pages = [];
		foreach ( $query->posts as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$pages[] = [
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'permalink' => (string) get_permalink( $post->ID ),
				'status'    => $post->post_status,
				'modified'  => $post->post_modified,
				'divi5'     => DiviPage::is_divi5_post( $post->ID ),
			];
		}

		return [
			'total' => (int) $query->found_posts,
			'pages' => $pages,
		];
	}
}
