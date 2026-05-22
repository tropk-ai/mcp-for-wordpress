<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Comments;

use Tropk\Mcp\Abilities\AbstractAbility;

final class CommentsReplyAbility extends AbstractAbility {

	public function slug(): string {
		return 'comments-reply';
	}

	protected function meta(): array {
		return [
			'label'       => __( 'Reply to a comment', 'mcp-for-wordpress' ),
			'description' => __( 'Posts a reply as a child of an existing comment.', 'mcp-for-wordpress' ),
		];
	}

	protected function input_schema(): array {
		return [
			'additionalProperties' => false,
			'required'             => [ 'parent_id', 'content' ],
			'properties'           => [
				'parent_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
				'content'      => [ 'type' => 'string', 'minLength' => 1 ],
				'author'       => [ 'type' => 'string' ],
				'author_email' => [ 'type' => 'string' ],
				'author_url'   => [ 'type' => 'string' ],
				'status'       => [ 'type' => 'string', 'enum' => [ 'approve', 'hold' ], 'default' => 'approve' ],
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
		$parent = get_comment( (int) $input['parent_id'] );
		if ( ! $parent instanceof \WP_Comment ) {
			throw new \RuntimeException( 'Parent comment not found.' );
		}
		$id = wp_insert_comment( [
			'comment_post_ID'      => (int) $parent->comment_post_ID,
			'comment_parent'       => (int) $parent->comment_ID,
			'comment_content'      => (string) $input['content'],
			'comment_author'       => (string) ( $input['author'] ?? '' ),
			'comment_author_email' => (string) ( $input['author_email'] ?? '' ),
			'comment_author_url'   => (string) ( $input['author_url'] ?? '' ),
			'comment_approved'     => ( ( $input['status'] ?? 'approve' ) === 'approve' ? 1 : 0 ),
		] );
		if ( ! $id ) {
			throw new \RuntimeException( 'Reply insert failed.' );
		}
		return [ 'created' => true, 'comment_id' => (int) $id ];
	}
}
