<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Comments;

use Tropk\Mcp\Abilities\AbstractAbility;

final class CommentsCreateAbility extends AbstractAbility {

	public function slug(): string {
		return 'comments-create';
	}

	protected function meta(): array {
		return [
			'label'       => __( 'Create a comment', 'mcp-for-wordpress' ),
			'description' => __( 'Inserts a new comment on a post.', 'mcp-for-wordpress' ),
			'destructive' => false,
		];
	}

	protected function input_schema(): array {
		return [
			'additionalProperties' => false,
			'required'             => [ 'post_id', 'content' ],
			'properties'           => [
				'post_id'      => [ 'type' => 'integer', 'minimum' => 1 ],
				'content'      => [ 'type' => 'string', 'minLength' => 1 ],
				'author'       => [ 'type' => 'string' ],
				'author_email' => [ 'type' => 'string', 'format' => 'email' ],
				'author_url'   => [ 'type' => 'string', 'format' => 'uri' ],
				'parent_id'    => [ 'type' => 'integer', 'minimum' => 0 ],
				'status'       => [ 'type' => 'string', 'enum' => [ 'approve', 'hold' ], 'default' => 'hold' ],
				'dry_run'      => [ 'type' => 'boolean', 'default' => false ],
			],
		];
	}

	protected function output_schema(): array {
		return [ 'properties' => [ 'created' => [ 'type' => 'boolean' ], 'comment_id' => [ 'type' => [ 'integer', 'null' ] ] ] ];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'moderate_comments' );
	}

	public function execute( array $input = [] ): array {
		if ( ! empty( $input['dry_run'] ) ) {
			return [ 'created' => false, 'comment_id' => null ];
		}
		$id = wp_insert_comment( [
			'comment_post_ID'      => (int) $input['post_id'],
			'comment_content'      => (string) $input['content'],
			'comment_author'       => (string) ( $input['author'] ?? '' ),
			'comment_author_email' => (string) ( $input['author_email'] ?? '' ),
			'comment_author_url'   => (string) ( $input['author_url'] ?? '' ),
			'comment_parent'       => (int) ( $input['parent_id'] ?? 0 ),
			'comment_approved'     => ( ( $input['status'] ?? 'hold' ) === 'approve' ? 1 : 0 ),
		] );
		if ( ! $id ) {
			throw new \RuntimeException( 'wp_insert_comment failed.' );
		}
		return [ 'created' => true, 'comment_id' => (int) $id ];
	}
}
