<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Comments;

use Tropk\Mcp\Abilities\AbstractAbility;

final class CommentsListAbility extends AbstractAbility {

	public function slug(): string {
		return 'comments-list';
	}

	protected function meta(): array {
		return [
			'label'       => __( 'List comments', 'mcp-for-wordpress' ),
			'description' => __( 'Lists comments with status, post, author and content filters.', 'mcp-for-wordpress' ),
			'readonly'    => true,
			'idempotent'  => true,
		];
	}

	protected function input_schema(): array {
		return [
			'additionalProperties' => false,
			'properties'           => [
				'post_id' => [ 'type' => 'integer', 'minimum' => 1 ],
				'status'  => [ 'type' => 'string', 'enum' => [ 'approve', 'hold', 'spam', 'trash', 'all' ], 'default' => 'approve' ],
				'search'  => [ 'type' => 'string' ],
				'author_email' => [ 'type' => 'string' ],
				'limit'   => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				'offset'  => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0 ],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'properties' => [
				'items'    => [ 'type' => 'array' ],
				'pageInfo' => [ 'type' => 'object' ],
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'moderate_comments' );
	}

	public function execute( array $input = [] ): array {
		$args = [
			'status' => (string) ( $input['status'] ?? 'approve' ),
			'number' => (int) ( $input['limit'] ?? 20 ),
			'offset' => (int) ( $input['offset'] ?? 0 ),
		];
		if ( isset( $input['post_id'] ) ) {
			$args['post_id'] = (int) $input['post_id'];
		}
		if ( isset( $input['search'] ) ) {
			$args['search'] = (string) $input['search'];
		}
		if ( isset( $input['author_email'] ) ) {
			$args['author_email'] = (string) $input['author_email'];
		}

		$query = new \WP_Comment_Query( $args );
		$items = [];
		foreach ( $query->get_comments() as $c ) {
			if ( ! $c instanceof \WP_Comment ) {
				continue;
			}
			$items[] = [
				'id'            => (int) $c->comment_ID,
				'post_id'       => (int) $c->comment_post_ID,
				'author'        => (string) $c->comment_author,
				'author_email'  => (string) $c->comment_author_email,
				'author_url'    => (string) $c->comment_author_url,
				'content'       => (string) $c->comment_content,
				'status'        => (string) wp_get_comment_status( $c ),
				'date'          => (string) $c->comment_date_gmt,
				'parent'        => (int) $c->comment_parent,
				'agent'         => (string) $c->comment_agent,
			];
		}

		return [
			'items'    => $items,
			'pageInfo' => [
				'limit'   => $args['number'],
				'offset'  => $args['offset'],
				'count'   => count( $items ),
			],
		];
	}
}
