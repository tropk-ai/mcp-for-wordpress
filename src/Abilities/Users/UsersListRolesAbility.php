<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Users;
use Tropk\Mcp\Abilities\AbstractAbility;
final class UsersListRolesAbility extends AbstractAbility {
	public function slug(): string { return 'users-list-roles'; }
	protected function meta(): array { return [ 'label' => __( 'List WordPress roles', 'mcp-for-wordpress' ), 'description' => __( 'Returns every role on the site with its capability list.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'roles' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'list_users' ); }
	public function execute( array $input = [] ): array {
		$out = [];
		foreach ( wp_roles()->roles as $slug => $r ) {
			$out[] = [ 'slug' => (string) $slug, 'name' => (string) ( $r['name'] ?? '' ), 'capabilities' => array_keys( (array) ( $r['capabilities'] ?? [] ) ) ];
		}
		return [ 'roles' => $out ];
	}
}
