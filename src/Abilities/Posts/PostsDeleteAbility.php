<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Posts;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;

final class PostsDeleteAbility extends AbstractAbility {

	public function slug(): string {
		return 'posts-delete';
	}

	protected function meta(): array {
		return [
			'label'       => __( 'Delete a post', 'mcp-for-wordpress' ),
			'description' => __( 'Trashes a post by default, or force-deletes when force=true. Always snapshots before deletion.', 'mcp-for-wordpress' ),
			'destructive' => true,
		];
	}

	protected function input_schema(): array {
		return [
			'additionalProperties' => false,
			'required'             => [ 'id' ],
			'properties'           => [
				'id'      => [ 'type' => 'integer', 'minimum' => 1 ],
				'force'   => [ 'type' => 'boolean', 'default' => false ],
				'dry_run' => [ 'type' => 'boolean', 'default' => false ],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'properties' => [
				'deleted'     => [ 'type' => 'boolean' ],
				'dry_run'     => [ 'type' => 'boolean' ],
				'force'       => [ 'type' => 'boolean' ],
				'post_id'     => [ 'type' => 'integer' ],
				'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['id'] ?? 0 );
		return $id > 0 && current_user_can( 'delete_post', $id ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}

	public function execute( array $input = [] ): array {
		$id      = (int) $input['id'];
		$force   = (bool) ( $input['force'] ?? false );
		$dry_run = (bool) ( $input['dry_run'] ?? false );
		$post    = get_post( $id );
		if ( ! $post instanceof \WP_Post ) {
			throw new \RuntimeException( sprintf( 'Post %d not found.', $id ) );
		}
		if ( $dry_run ) {
			return [ 'deleted' => false, 'dry_run' => true, 'force' => $force, 'post_id' => $id, 'snapshot_id' => null ];
		}
		$snapshot = ( new SnapshotManager() )->snapshot_post( $id, $force ? 'posts-delete:force' : 'posts-delete:trash' );
		$result   = $force ? wp_delete_post( $id, true ) : wp_trash_post( $id );
		if ( ! $result ) {
			throw new \RuntimeException( 'Delete operation failed.' );
		}
		return [ 'deleted' => true, 'dry_run' => false, 'force' => $force, 'post_id' => $id, 'snapshot_id' => $snapshot['snapshot_id'] ];
	}
}
