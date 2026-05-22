<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Users;

use Tropk\Mcp\Abilities\AbstractAbility;

final class UsersListAbility extends AbstractAbility {

	public function slug(): string {
		return 'users-list';
	}

	protected function meta(): array {
		return [
			'label'       => __( 'List users', 'mcp-for-wordpress' ),
			'description' => __( 'Lists users with role / search / pagination filters.', 'mcp-for-wordpress' ),
			'readonly'    => true,
			'idempotent'  => true,
		];
	}

	protected function input_schema(): array {
		return [
			'additionalProperties' => false,
			'properties'           => [
				'role'    => [ 'type' => 'string' ],
				'search'  => [ 'type' => 'string' ],
				'limit'   => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				'offset'  => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0 ],
				'orderby' => [ 'type' => 'string', 'enum' => [ 'ID', 'login', 'email', 'registered' ], 'default' => 'registered' ],
				'order'   => [ 'type' => 'string', 'enum' => [ 'ASC', 'DESC' ], 'default' => 'DESC' ],
			],
		];
	}

	protected function output_schema(): array {
		return [ 'properties' => [ 'items' => [ 'type' => 'array' ], 'pageInfo' => [ 'type' => 'object' ] ] ];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'list_users' );
	}

	public function execute( array $input = [] ): array {
		$args = [
			'number'  => (int) ( $input['limit'] ?? 20 ),
			'offset'  => (int) ( $input['offset'] ?? 0 ),
			'orderby' => (string) ( $input['orderby'] ?? 'registered' ),
			'order'   => (string) ( $input['order'] ?? 'DESC' ),
			'fields'  => 'all',
		];
		if ( isset( $input['role'] ) ) {
			$args['role'] = (string) $input['role'];
		}
		if ( isset( $input['search'] ) ) {
			$args['search'] = '*' . esc_attr( (string) $input['search'] ) . '*';
		}
		$users = get_users( $args );
		$items = [];
		foreach ( $users as $u ) {
			$items[] = [
				'id'         => (int) $u->ID,
				'login'      => (string) $u->user_login,
				'email'      => (string) $u->user_email,
				'name'       => (string) $u->display_name,
				'roles'      => array_values( (array) $u->roles ),
				'registered' => (string) $u->user_registered,
			];
		}
		return [
			'items'    => $items,
			'pageInfo' => [ 'limit' => $args['number'], 'offset' => $args['offset'], 'count' => count( $items ) ],
		];
	}
}
