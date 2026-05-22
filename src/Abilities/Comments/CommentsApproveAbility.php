<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Comments;
use Tropk\Mcp\Abilities\AbstractAbility;
final class CommentsApproveAbility extends AbstractAbility {
	public function slug(): string { return 'comments-approve'; }
	protected function meta(): array { return [ 'label' => __( 'Approve a comment', 'mcp-for-wordpress' ), 'description' => __( 'Shortcut for comments-update-status with status=approve.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'id' ], 'properties' => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'approved' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'moderate_comments' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array { return [ 'approved' => (bool) wp_set_comment_status( (int) $input['id'], 'approve' ) ]; }
}
