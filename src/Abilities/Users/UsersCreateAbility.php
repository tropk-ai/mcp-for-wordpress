<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Users;

use Tropk\Mcp\Abilities\AbstractAbility;

final class UsersCreateAbility extends AbstractAbility {

	public function slug(): string {
		return 'users-create';
	}

	protected function meta(): array {
		return [
			'label'       => __( 'Create a user', 'mcp-for-wordpress' ),
			'description' => __( 'Creates a new WordPress user with the given role. Password is auto-generated if not supplied.', 'mcp-for-wordpress' ),
			'destructive' => false,
		];
	}

	protected function input_schema(): array {
		return [
			'additionalProperties' => false,
			'required'             => [ 'login', 'email' ],
			'properties'           => [
				'login'        => [ 'type' => 'string', 'minLength' => 3 ],
				'email'        => [ 'type' => 'string', 'format' => 'email' ],
				'password'     => [ 'type' => 'string', 'minLength' => 8 ],
				'role'         => [ 'type' => 'string', 'default' => 'subscriber' ],
				'first_name'   => [ 'type' => 'string' ],
				'last_name'    => [ 'type' => 'string' ],
				'display_name' => [ 'type' => 'string' ],
				'send_notification' => [ 'type' => 'boolean', 'default' => false ],
			],
		];
	}

	protected function output_schema(): array {
		return [ 'properties' => [ 'created' => [ 'type' => 'boolean' ], 'user_id' => [ 'type' => [ 'integer', 'null' ] ] ] ];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'create_users' );
	}

	public function execute( array $input = [] ): array {
		$password = (string) ( $input['password'] ?? wp_generate_password( 16 ) );
		$user_id  = wp_insert_user( [
			'user_login'   => (string) $input['login'],
			'user_email'   => (string) $input['email'],
			'user_pass'    => $password,
			'role'         => (string) ( $input['role'] ?? 'subscriber' ),
			'first_name'   => (string) ( $input['first_name'] ?? '' ),
			'last_name'    => (string) ( $input['last_name'] ?? '' ),
			'display_name' => (string) ( $input['display_name'] ?? $input['login'] ),
		] );
		if ( is_wp_error( $user_id ) ) {
			throw new \RuntimeException( 'wp_insert_user failed: ' . $user_id->get_error_message() );
		}
		if ( ! empty( $input['send_notification'] ) ) {
			wp_new_user_notification( (int) $user_id, null, 'both' );
		}
		return [ 'created' => true, 'user_id' => (int) $user_id ];
	}
}
