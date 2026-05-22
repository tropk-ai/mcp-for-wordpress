<?php
/**
 * User roles & capabilities abilities for the Abilities API.
 *
 * Registers 8 abilities under the `roles/*` namespace covering role
 * discovery, role create/delete, capability add/remove, role assignment
 * and capability checks.
 *
 * @package Tropk\Mcp\Extras
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_abilities_api_init',
	static function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'tropk-roles/list',
			[
				'label'               => 'Roles: list',
     'category'            => 'tropk-core',
				'description'         => 'List all roles with their display name and capabilities.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					$roles = wp_roles();
					$out   = [];
					foreach ( $roles->roles as $slug => $role ) {
						$out[] = [
							'slug'         => $slug,
							'name'         => $role['name'] ?? $slug,
							'capabilities' => array_keys( array_filter( (array) ( $role['capabilities'] ?? [] ) ) ),
						];
					}
					return [ 'roles' => $out, 'count' => count( $out ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'list_users' ) || current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-roles/get-capabilities',
			[
				'label'               => 'Roles: get capabilities',
     'category'            => 'tropk-core',
				'description'         => 'Get the capabilities granted to a role.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'role' ],
					'properties' => [ 'role' => [ 'type' => 'string' ] ],
				],
				'execute_callback'    => static function ( array $input ): array {
					$role = get_role( (string) $input['role'] );
					if ( ! $role ) {
						throw new \RuntimeException( sprintf( 'Role "%s" not found.', (string) $input['role'] ) );
					}
					return [ 'role' => (string) $input['role'], 'capabilities' => array_keys( array_filter( $role->capabilities ) ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'list_users' ) || current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-roles/create',
			[
				'label'               => 'Roles: create',
     'category'            => 'tropk-core',
				'description'         => 'Create a new role.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'slug', 'name' ],
					'properties' => [
						'slug'         => [ 'type' => 'string', 'pattern' => '^[a-z0-9_-]+$' ],
						'name'         => [ 'type' => 'string' ],
						'capabilities' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$caps = [];
					foreach ( (array) ( $input['capabilities'] ?? [] ) as $c ) {
						$caps[ (string) $c ] = true;
					}
					$role = add_role( (string) $input['slug'], (string) $input['name'], $caps );
					if ( null === $role ) {
						throw new \RuntimeException( sprintf( 'Role "%s" already exists.', (string) $input['slug'] ) );
					}
					return [ 'created' => true, 'slug' => (string) $input['slug'] ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => false ] ],
			]
		);

		wp_register_ability(
			'tropk-roles/delete',
			[
				'label'               => 'Roles: delete',
     'category'            => 'tropk-core',
				'description'         => 'Delete an existing role. Refuses to delete built-in WordPress roles.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'slug' ],
					'properties' => [ 'slug' => [ 'type' => 'string' ] ],
				],
				'execute_callback'    => static function ( array $input ): array {
					$slug  = (string) $input['slug'];
					$built = [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ];
					if ( in_array( $slug, $built, true ) ) {
						throw new \RuntimeException( 'Refusing to delete a built-in role.' );
					}
					remove_role( $slug );
					return [ 'deleted' => true, 'slug' => $slug ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-roles/add-capability',
			[
				'label'               => 'Roles: add capability',
     'category'            => 'tropk-core',
				'description'         => 'Grant a capability to a role.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'role', 'capability' ],
					'properties' => [
						'role'       => [ 'type' => 'string' ],
						'capability' => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$role = get_role( (string) $input['role'] );
					if ( ! $role ) {
						throw new \RuntimeException( sprintf( 'Role "%s" not found.', (string) $input['role'] ) );
					}
					$role->add_cap( (string) $input['capability'] );
					return [ 'updated' => true, 'role' => (string) $input['role'], 'capability' => (string) $input['capability'] ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-roles/remove-capability',
			[
				'label'               => 'Roles: remove capability',
     'category'            => 'tropk-core',
				'description'         => 'Revoke a capability from a role.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'role', 'capability' ],
					'properties' => [
						'role'       => [ 'type' => 'string' ],
						'capability' => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$role = get_role( (string) $input['role'] );
					if ( ! $role ) {
						throw new \RuntimeException( sprintf( 'Role "%s" not found.', (string) $input['role'] ) );
					}
					$role->remove_cap( (string) $input['capability'] );
					return [ 'updated' => true ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-roles/assign-to-user',
			[
				'label'               => 'Roles: assign to user',
     'category'            => 'tropk-core',
				'description'         => 'Replace a user\'s roles with the given role (or append it).',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'user_id', 'role' ],
					'properties' => [
						'user_id' => [ 'type' => 'integer', 'minimum' => 1 ],
						'role'    => [ 'type' => 'string' ],
						'append'  => [ 'type' => 'boolean', 'default' => false ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$user = get_userdata( (int) $input['user_id'] );
					if ( ! $user ) {
						throw new \RuntimeException( 'User not found.' );
					}
					if ( (bool) ( $input['append'] ?? false ) ) {
						$user->add_role( (string) $input['role'] );
					} else {
						$user->set_role( (string) $input['role'] );
					}
					return [ 'updated' => true, 'user_id' => (int) $input['user_id'], 'roles' => $user->roles ];
				},
				'permission_callback' => static fn() => current_user_can( 'promote_users' ) || current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-roles/check-user-capability',
			[
				'label'               => 'Roles: check user capability',
     'category'            => 'tropk-core',
				'description'         => 'Check whether a given user has a capability.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'user_id', 'capability' ],
					'properties' => [
						'user_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
						'capability' => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$user = get_userdata( (int) $input['user_id'] );
					if ( ! $user ) {
						throw new \RuntimeException( 'User not found.' );
					}
					return [ 'user_id' => $user->ID, 'capability' => (string) $input['capability'], 'allowed' => user_can( $user, (string) $input['capability'] ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'list_users' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);
	},
	20
);
