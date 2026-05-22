<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Content;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ContentBulkDeletePostsAbility extends AbstractAbility {
	public function slug(): string { return 'content-bulk-delete-posts'; }
	protected function meta(): array { return [ 'label' => __( 'Bulk-delete posts', 'mcp-for-wordpress' ), 'description' => __( 'Trashes (or force-deletes) many posts in one call.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required' => [ 'ids' ],
		'properties' => [
			'ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'maxItems' => 100 ],
			'force' => [ 'type' => 'boolean', 'default' => false ],
			'dry_run' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deleted' => [ 'type' => 'integer' ], 'failed' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$ids = (array) ( $input['ids'] ?? [] );
		$force = (bool) ( $input['force'] ?? false );
		if ( ! empty( $input['dry_run'] ) ) return [ 'deleted' => 0, 'failed' => count( $ids ), 'dry_run' => true ];
		$d = 0; $f = 0;
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( ! current_user_can( 'delete_post', $id ) ) { $f++; continue; }
			$ok = $force ? wp_delete_post( $id, true ) : wp_trash_post( $id );
			$ok ? $d++ : $f++;
		}
		return [ 'deleted' => $d, 'failed' => $f ];
	}
}
