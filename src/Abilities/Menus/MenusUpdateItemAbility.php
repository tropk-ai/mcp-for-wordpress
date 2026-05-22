<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Menus;

use Tropk\Mcp\Abilities\AbstractAbility;

final class MenusUpdateItemAbility extends AbstractAbility {
	public function slug(): string { return 'menus-update-item'; }
	protected function meta(): array { return [
		'label' => __( 'Update a menu item', 'mcp-for-wordpress' ),
		'description' => __( 'Updates an existing menu item (title, URL, parent, order, open-in-new-tab).', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'menu_id', 'item_id' ],
		'properties'           => [
			'menu_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'item_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'title'      => [ 'type' => 'string' ],
			'url'        => [ 'type' => 'string' ],
			'parent_id'  => [ 'type' => 'integer', 'minimum' => 0 ],
			'menu_order' => [ 'type' => 'integer' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_theme_options' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$args = [];
		foreach ( [ 'title' => 'menu-item-title', 'url' => 'menu-item-url', 'parent_id' => 'menu-item-parent-id', 'menu_order' => 'menu-item-position' ] as $in => $out ) {
			if ( isset( $input[ $in ] ) ) {
				$args[ $out ] = $input[ $in ];
			}
		}
		if ( empty( $args ) ) {
			return [ 'updated' => false ];
		}
		$id = wp_update_nav_menu_item( (int) $input['menu_id'], (int) $input['item_id'], $args );
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message() );
		}
		return [ 'updated' => true ];
	}
}
