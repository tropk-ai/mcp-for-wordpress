<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Users;

use Tropk\Mcp\Abilities\AbstractAbility;

final class UsersDeleteAbility extends AbstractAbility {

	public function slug(): string {
		return 'users-delete';
	}

	protected function meta(): array {
		return [
			'label'       => __( 'Delete a user', 'mcp-for-wordpress' ),
			'description' => __( 'Deletes a WordPress user. Optionally reassigns their content to another user.', 'mcp-for-wordpress' ),
			'destructive' => true,
		];
	}

	protected function input_schema(): array {
		return [
			'additionalProperties' => false,
			'required'             => [ 'id' ],
			'properties'           => [
				'id'        => [ 'type' => 'integer', 'minimum' => 1 ],
				'reassign'  => [ 'type' => 'integer', 'minimum' => 1, 'description' => __( 'User ID to reassign content to. If omitted, content is deleted with the user.', 'mcp-for-wordpress' ) ],
			],
		];
	}

	protected function output_schema(): array {
		return [ 'properties' => [ 'deleted' => [ 'type' => 'boolean' ] ] ];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'delete_users' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}

	public function execute( array $input = [] ): array {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$ok = wp_delete_user( (int) $input['id'], isset( $input['reassign'] ) ? (int) $input['reassign'] : null );
		return [ 'deleted' => (bool) $ok ];
	}
}
