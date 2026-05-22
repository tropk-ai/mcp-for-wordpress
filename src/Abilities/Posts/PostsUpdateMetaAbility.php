<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Posts;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;

class PostsUpdateMetaAbility extends AbstractAbility {

	private const PROTECTED_PREFIXES = [ '_edit_lock', '_edit_last' ];
	private const PROTECTED_KEYS     = [ '_edit_lock', '_edit_last', '_wp_attached_file', '_wp_attachment_metadata' ];

	public function slug(): string {
		return 'posts-update-meta';
	}

	protected function meta(): array {
		return [
			'label'       => __( 'Update post meta', 'mcp-for-wordpress' ),
			'description' => __( 'Sets or deletes a single postmeta entry. Snapshots the post first. Refuses protected core keys.', 'mcp-for-wordpress' ),
			'destructive' => true,
			'idempotent'  => true,
		];
	}

	protected function input_schema(): array {
		return [
			'additionalProperties' => false,
			'required'             => [ 'post_id', 'meta_key' ],
			'properties'           => [
				'post_id'  => [ 'type' => 'integer', 'minimum' => 1 ],
				'meta_key' => [ 'type' => 'string', 'minLength' => 1 ],
				'value'    => [ 'description' => __( 'Pass null to delete.', 'mcp-for-wordpress' ) ],
				'dry_run'  => [ 'type' => 'boolean', 'default' => false ],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'properties' => [
				'updated'     => [ 'type' => 'boolean' ],
				'deleted'     => [ 'type' => 'boolean' ],
				'dry_run'     => [ 'type' => 'boolean' ],
				'post_id'     => [ 'type' => 'integer' ],
				'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['post_id'] ?? 0 );
		return $id > 0 && current_user_can( 'edit_post', $id ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}

	public function execute( array $input = [] ): array {
		$post_id = (int) $input['post_id'];
		$key     = (string) $input['meta_key'];
		$dry_run = (bool) ( $input['dry_run'] ?? false );

		foreach ( self::PROTECTED_PREFIXES as $p ) {
			if ( str_starts_with( $key, $p ) ) {
				throw new \RuntimeException( sprintf( 'Meta key "%s" is reserved.', $key ) );
			}
		}
		if ( in_array( $key, self::PROTECTED_KEYS, true ) ) {
			throw new \RuntimeException( sprintf( 'Meta key "%s" is protected.', $key ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			throw new \RuntimeException( sprintf( 'Post %d not found.', $post_id ) );
		}

		$has_value = array_key_exists( 'value', $input );
		$delete    = $has_value && null === $input['value'];

		if ( $dry_run ) {
			return [ 'updated' => false, 'deleted' => false, 'dry_run' => true, 'post_id' => $post_id, 'snapshot_id' => null ];
		}

		$snapshot    = ( new SnapshotManager() )->snapshot_post( $post_id, 'posts-update-meta:' . $key );
		$snapshot_id = $snapshot['snapshot_id'];

		if ( $delete ) {
			delete_post_meta( $post_id, $key );
			return [ 'updated' => true, 'deleted' => true, 'dry_run' => false, 'post_id' => $post_id, 'snapshot_id' => $snapshot_id ];
		}
		update_post_meta( $post_id, $key, $input['value'] ?? '' );
		return [ 'updated' => true, 'deleted' => false, 'dry_run' => false, 'post_id' => $post_id, 'snapshot_id' => $snapshot_id ];
	}
}
