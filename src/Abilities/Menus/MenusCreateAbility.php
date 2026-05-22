<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Menus;

use Tropk\Mcp\Abilities\AbstractAbility;

final class MenusCreateAbility extends AbstractAbility {
	public function slug(): string { return 'menus-create'; }
	protected function meta(): array { return [
		'label' => __( 'Create a menu', 'mcp-for-wordpress' ),
		'description' => __( 'Creates an empty navigation menu by name.', 'mcp-for-wordpress' ),
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'name' ],
		'properties'           => [ 'name' => [ 'type' => 'string', 'minLength' => 1 ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'created' => [ 'type' => 'boolean' ], 'menu_id' => [ 'type' => [ 'integer', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_theme_options' ); }
	public function execute( array $input = [] ): array {
		$id = wp_create_nav_menu( (string) $input['name'] );
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message() );
		}
		return [ 'created' => true, 'menu_id' => (int) $id ];
	}
}
