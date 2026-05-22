<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Meta;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;

final class MetaDeletePostMetaAbility extends AbstractAbility {
	public function slug(): string { return 'meta-delete-post-meta'; }
	protected function meta(): array { return [
		'label' => __( 'Delete a postmeta entry', 'mcp-for-wordpress' ),
		'description' => __( 'Removes a single postmeta entry. Always snapshots the post first.', 'mcp-for-wordpress' ),
		'destructive' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'meta_key' ],
		'properties'           => [
			'post_id'  => [ 'type' => 'integer', 'minimum' => 1 ],
			'meta_key' => [ 'type' => 'string', 'minLength' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deleted' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['post_id'] ?? 0 );
		return $id > 0 && current_user_can( 'edit_post', $id ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$id  = (int) $input['post_id'];
		$key = (string) $input['meta_key'];
		if ( str_starts_with( $key, '_edit_' ) ) {
			throw new \RuntimeException( 'Refusing to delete an _edit_* reserved meta.' );
		}
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'meta-delete-post-meta:' . $key );
		delete_post_meta( $id, $key );
		return [ 'deleted' => true, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
