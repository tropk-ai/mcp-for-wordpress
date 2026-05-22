<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Menus;

use Tropk\Mcp\Abilities\AbstractAbility;

final class MenusAssignLocationAbility extends AbstractAbility {
	public function slug(): string { return 'menus-assign-location'; }
	protected function meta(): array { return [
		'label' => __( 'Assign menu to a location', 'mcp-for-wordpress' ),
		'description' => __( 'Assigns a menu to a theme location (e.g. primary, footer).', 'mcp-for-wordpress' ),
		'destructive' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'location', 'menu_id' ],
		'properties'           => [
			'location' => [ 'type' => 'string' ],
			'menu_id'  => [ 'type' => 'integer', 'minimum' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'assigned' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_theme_options' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$locations = (array) get_theme_mod( 'nav_menu_locations' );
		$locations[ (string) $input['location'] ] = (int) $input['menu_id'];
		set_theme_mod( 'nav_menu_locations', $locations );
		return [ 'assigned' => true ];
	}
}
