<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Content;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
final class ContentDeletePageAbility extends AbstractAbility {
	public function slug(): string { return 'content-delete-page'; }
	protected function meta(): array { return [ 'label' => __( 'Delete a page', 'mcp-for-wordpress' ), 'description' => __( 'Trashes (or force-deletes) a page. Snapshots first.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'id' ], 'properties' => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ], 'force' => [ 'type' => 'boolean', 'default' => false ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deleted' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'delete_post', (int) ( $input['id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$snap = ( new SnapshotManager() )->snapshot_post( (int) $input['id'], 'content-delete-page' );
		$ok = ! empty( $input['force'] ) ? wp_delete_post( (int) $input['id'], true ) : wp_trash_post( (int) $input['id'] );
		return [ 'deleted' => (bool) $ok, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
