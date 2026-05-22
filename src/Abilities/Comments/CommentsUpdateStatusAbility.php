<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Comments;

use Tropk\Mcp\Abilities\AbstractAbility;

final class CommentsUpdateStatusAbility extends AbstractAbility {

	public function slug(): string {
		return 'comments-update-status';
	}

	protected function meta(): array {
		return [
			'label'       => __( 'Update comment status', 'mcp-for-wordpress' ),
			'description' => __( 'Approves, holds, marks as spam, or trashes a comment.', 'mcp-for-wordpress' ),
			'destructive' => true,
			'idempotent'  => true,
		];
	}

	protected function input_schema(): array {
		return [
			'additionalProperties' => false,
			'required'             => [ 'id', 'status' ],
			'properties'           => [
				'id'     => [ 'type' => 'integer', 'minimum' => 1 ],
				'status' => [ 'type' => 'string', 'enum' => [ 'approve', 'hold', 'spam', 'trash' ] ],
			],
		];
	}

	protected function output_schema(): array {
		return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'status' => [ 'type' => 'string' ] ] ];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'moderate_comments' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}

	public function execute( array $input = [] ): array {
		$id     = (int) $input['id'];
		$status = (string) $input['status'];
		$ok     = false;
		switch ( $status ) {
			case 'approve':
				$ok = (bool) wp_set_comment_status( $id, 'approve' );
				break;
			case 'hold':
				$ok = (bool) wp_set_comment_status( $id, 'hold' );
				break;
			case 'spam':
				$ok = (bool) wp_spam_comment( $id );
				break;
			case 'trash':
				$ok = (bool) wp_trash_comment( $id );
				break;
		}
		return [ 'updated' => $ok, 'status' => $status ];
	}
}
