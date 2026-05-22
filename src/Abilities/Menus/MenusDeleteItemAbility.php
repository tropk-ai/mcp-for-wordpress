<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Menus;

use Tropk\Mcp\Abilities\AbstractAbility;

final class MenusDeleteItemAbility extends AbstractAbility {
	public function slug(): string { return 'menus-delete-item'; }
	protected function meta(): array { return [
		'label' => __( 'Delete a menu item', 'mcp-for-wordpress' ),
		'description' => __( 'Removes a single item from a menu.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'item_id' ],
		'properties'           => [ 'item_id' => [ 'type' => 'integer', 'minimum' => 1 ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deleted' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_theme_options' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$res = wp_delete_post( (int) $input['item_id'], true );
		return [ 'deleted' => (bool) $res ];
	}
}
