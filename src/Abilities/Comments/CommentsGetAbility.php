<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Comments;

use Tropk\Mcp\Abilities\AbstractAbility;

final class CommentsGetAbility extends AbstractAbility {

	public function slug(): string {
		return 'comments-get';
	}

	protected function meta(): array {
		return [
			'label'       => __( 'Get a comment', 'mcp-for-wordpress' ),
			'description' => __( 'Returns a single comment by ID.', 'mcp-for-wordpress' ),
			'readonly'    => true,
			'idempotent'  => true,
		];
	}

	protected function input_schema(): array {
		return [
			'additionalProperties' => false,
			'required'             => [ 'id' ],
			'properties'           => [
				'id' => [ 'type' => 'integer', 'minimum' => 1 ],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'properties' => [
				'id'           => [ 'type' => 'integer' ],
				'post_id'      => [ 'type' => 'integer' ],
				'author'       => [ 'type' => 'string' ],
				'author_email' => [ 'type' => 'string' ],
				'content'      => [ 'type' => 'string' ],
				'status'       => [ 'type' => 'string' ],
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'moderate_comments' );
	}

	public function execute( array $input = [] ): array {
		$c = get_comment( (int) $input['id'] );
		if ( ! $c instanceof \WP_Comment ) {
			throw new \RuntimeException( sprintf( 'Comment %d not found.', (int) $input['id'] ) );
		}
		return [
			'id'            => (int) $c->comment_ID,
			'post_id'       => (int) $c->comment_post_ID,
			'author'        => (string) $c->comment_author,
			'author_email'  => (string) $c->comment_author_email,
			'author_url'    => (string) $c->comment_author_url,
			'content'       => (string) $c->comment_content,
			'status'        => (string) wp_get_comment_status( $c ),
			'date'          => (string) $c->comment_date_gmt,
			'parent'        => (int) $c->comment_parent,
		];
	}
}
