<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Meta;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;

final class MetaUpdatePostMetaAbility extends AbstractAbility {
	public function slug(): string { return 'meta-update-post-meta'; }
	protected function meta(): array { return [
		'label' => __( 'Update post meta (alias)', 'mcp-for-wordpress' ),
		'description' => __( 'Sets a single postmeta entry. Snapshots the post first. Same as posts/update-meta, exposed under the meta/* namespace.', 'mcp-for-wordpress' ),
		'destructive' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'meta_key', 'value' ],
		'properties'           => [
			'post_id'  => [ 'type' => 'integer', 'minimum' => 1 ],
			'meta_key' => [ 'type' => 'string', 'minLength' => 1 ],
			'value'    => [],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['post_id'] ?? 0 );
		return $id > 0 && current_user_can( 'edit_post', $id ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$id  = (int) $input['post_id'];
		$key = (string) $input['meta_key'];
		if ( str_starts_with( $key, '_edit_' ) ) {
			throw new \RuntimeException( 'Refusing to write an _edit_* reserved meta.' );
		}
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'meta-update-post-meta:' . $key );
		update_post_meta( $id, $key, $input['value'] );
		return [ 'updated' => true, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
