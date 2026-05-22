<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Comments;
use Tropk\Mcp\Abilities\AbstractAbility;
final class CommentsBulkDeleteAbility extends AbstractAbility {
	public function slug(): string { return 'comments-bulk-delete'; }
	protected function meta(): array { return [ 'label' => __( 'Bulk-delete comments', 'mcp-for-wordpress' ), 'description' => __( 'Trashes (or force-deletes) many comments at once.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'ids' ], 'properties' => [ 'ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'maxItems' => 200 ], 'force' => [ 'type' => 'boolean', 'default' => false ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deleted' => [ 'type' => 'integer' ], 'failed' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'moderate_comments' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$force = (bool) ( $input['force'] ?? false );
		$d = 0; $f = 0;
		foreach ( (array) $input['ids'] as $id ) {
			wp_delete_comment( (int) $id, $force ) ? $d++ : $f++;
		}
		return [ 'deleted' => $d, 'failed' => $f ];
	}
}
