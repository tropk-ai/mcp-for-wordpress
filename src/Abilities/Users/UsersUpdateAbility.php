<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Users;

use Tropk\Mcp\Abilities\AbstractAbility;

final class UsersUpdateAbility extends AbstractAbility {

	public function slug(): string {
		return 'users-update';
	}

	protected function meta(): array {
		return [
			'label'       => __( 'Update a user', 'mcp-for-wordpress' ),
			'description' => __( 'Patches a WordPress user: email, password, role, display name, etc.', 'mcp-for-wordpress' ),
			'destructive' => true,
		];
	}

	protected function input_schema(): array {
		return [
			'additionalProperties' => false,
			'required'             => [ 'id' ],
			'properties'           => [
				'id'           => [ 'type' => 'integer', 'minimum' => 1 ],
				'email'        => [ 'type' => 'string' ],
				'password'     => [ 'type' => 'string', 'minLength' => 8 ],
				'role'         => [ 'type' => 'string' ],
				'first_name'   => [ 'type' => 'string' ],
				'last_name'    => [ 'type' => 'string' ],
				'display_name' => [ 'type' => 'string' ],
				'url'          => [ 'type' => 'string' ],
			],
		];
	}

	protected function output_schema(): array {
		return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ] ] ];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_user', (int) ( $input['id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}

	public function execute( array $input = [] ): array {
		$id   = (int) $input['id'];
		$args = [ 'ID' => $id ];
		foreach ( [ 'email' => 'user_email', 'password' => 'user_pass', 'first_name' => 'first_name', 'last_name' => 'last_name', 'display_name' => 'display_name', 'url' => 'user_url' ] as $in => $out ) {
			if ( isset( $input[ $in ] ) ) {
				$args[ $out ] = (string) $input[ $in ];
			}
		}
		if ( isset( $input['role'] ) ) {
			$args['role'] = (string) $input['role'];
		}
		$res = wp_update_user( $args );
		if ( is_wp_error( $res ) ) {
			throw new \RuntimeException( 'wp_update_user failed: ' . $res->get_error_message() );
		}
		return [ 'updated' => true ];
	}
}
