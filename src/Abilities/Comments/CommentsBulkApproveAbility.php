<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Comments;
use Tropk\Mcp\Abilities\AbstractAbility;
final class CommentsBulkApproveAbility extends AbstractAbility {
	public function slug(): string { return 'comments-bulk-approve'; }
	protected function meta(): array { return [ 'label' => __( 'Bulk-approve comments', 'mcp-for-wordpress' ), 'description' => __( 'Approves many comments by ID in one call.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'ids' ], 'properties' => [ 'ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'maxItems' => 200 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'approved' => [ 'type' => 'integer' ], 'failed' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'moderate_comments' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$a = 0; $f = 0;
		foreach ( (array) $input['ids'] as $id ) {
			wp_set_comment_status( (int) $id, 'approve' ) ? $a++ : $f++;
		}
		return [ 'approved' => $a, 'failed' => $f ];
	}
}
