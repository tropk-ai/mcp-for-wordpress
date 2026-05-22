<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Users;
use Tropk\Mcp\Abilities\AbstractAbility;
final class UsersRemoveRoleAbility extends AbstractAbility {
	public function slug(): string { return 'users-remove-role'; }
	protected function meta(): array { return [ 'label' => __( 'Remove a role from a user', 'mcp-for-wordpress' ), 'description' => __( 'Revokes a specific role from a user.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'id', 'role' ], 'properties' => [ 'id' => [ 'type' => 'integer' ], 'role' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'removed' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'promote_users' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$u = get_userdata( (int) $input['id'] );
		if ( ! $u instanceof \WP_User ) throw new \RuntimeException( 'User not found.' );
		$u->remove_role( (string) $input['role'] );
		return [ 'removed' => true ];
	}
}
