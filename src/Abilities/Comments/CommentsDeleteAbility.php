<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Comments;

use Tropk\Mcp\Abilities\AbstractAbility;

final class CommentsDeleteAbility extends AbstractAbility {

	public function slug(): string {
		return 'comments-delete';
	}

	protected function meta(): array {
		return [
			'label'       => __( 'Delete a comment', 'mcp-for-wordpress' ),
			'description' => __( 'Permanently deletes a comment (or trashes it when force=false).', 'mcp-for-wordpress' ),
			'destructive' => true,
		];
	}

	protected function input_schema(): array {
		return [
			'additionalProperties' => false,
			'required'             => [ 'id' ],
			'properties'           => [
				'id'    => [ 'type' => 'integer', 'minimum' => 1 ],
				'force' => [ 'type' => 'boolean', 'default' => false ],
			],
		];
	}

	protected function output_schema(): array {
		return [ 'properties' => [ 'deleted' => [ 'type' => 'boolean' ], 'force' => [ 'type' => 'boolean' ] ] ];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'moderate_comments' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}

	public function execute( array $input = [] ): array {
		$id    = (int) $input['id'];
		$force = (bool) ( $input['force'] ?? false );
		$ok    = (bool) wp_delete_comment( $id, $force );
		return [ 'deleted' => $ok, 'force' => $force ];
	}
}
