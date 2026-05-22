<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Users;

use Tropk\Mcp\Abilities\AbstractAbility;

final class UsersGetAbility extends AbstractAbility {

	public function slug(): string {
		return 'users-get';
	}

	protected function meta(): array {
		return [
			'label'       => __( 'Get a user', 'mcp-for-wordpress' ),
			'description' => __( 'Returns a single user by ID, login or email.', 'mcp-for-wordpress' ),
			'readonly'    => true,
			'idempotent'  => true,
		];
	}

	protected function input_schema(): array {
		return [
			'additionalProperties' => false,
			'properties'           => [
				'id'    => [ 'type' => 'integer', 'minimum' => 1 ],
				'login' => [ 'type' => 'string' ],
				'email' => [ 'type' => 'string' ],
			],
		];
	}

	protected function output_schema(): array {
		return [ 'properties' => [ 'id' => [ 'type' => 'integer' ] ] ];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'list_users' ) || (
			isset( $input['id'] ) && (int) $input['id'] === get_current_user_id()
		);
	}

	public function execute( array $input = [] ): array {
		$user = null;
		if ( ! empty( $input['id'] ) ) {
			$user = get_userdata( (int) $input['id'] );
		} elseif ( ! empty( $input['login'] ) ) {
			$user = get_user_by( 'login', (string) $input['login'] );
		} elseif ( ! empty( $input['email'] ) ) {
			$user = get_user_by( 'email', (string) $input['email'] );
		}
		if ( ! $user instanceof \WP_User ) {
			throw new \RuntimeException( 'User not found.' );
		}
		return [
			'id'           => (int) $user->ID,
			'login'        => (string) $user->user_login,
			'email'        => (string) $user->user_email,
			'display_name' => (string) $user->display_name,
			'first_name'   => (string) $user->first_name,
			'last_name'    => (string) $user->last_name,
			'roles'        => array_values( (array) $user->roles ),
			'caps'         => array_keys( (array) $user->allcaps ),
			'registered'   => (string) $user->user_registered,
			'url'          => (string) $user->user_url,
		];
	}
}
