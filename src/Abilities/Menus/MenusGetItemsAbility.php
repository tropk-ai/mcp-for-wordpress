<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Menus;

use Tropk\Mcp\Abilities\AbstractAbility;

final class MenusGetItemsAbility extends AbstractAbility {
	public function slug(): string { return 'menus-get-items'; }
	protected function meta(): array { return [
		'label' => __( 'Get menu items', 'mcp-for-wordpress' ),
		'description' => __( 'Returns every item in a menu, with title, type, URL and parent.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'menu_id' ],
		'properties'           => [ 'menu_id' => [ 'type' => 'integer', 'minimum' => 1 ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'items' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_theme_options' ); }
	public function execute( array $input = [] ): array {
		$items = wp_get_nav_menu_items( (int) $input['menu_id'] );
		$out   = [];
		if ( is_array( $items ) ) {
			foreach ( $items as $i ) {
				$out[] = [
					'id'        => (int) $i->ID,
					'title'     => (string) $i->title,
					'url'       => (string) $i->url,
					'parent_id' => (int) $i->menu_item_parent,
					'order'     => (int) $i->menu_order,
					'type'      => (string) $i->type,
					'object'    => (string) $i->object,
					'object_id' => (int) $i->object_id,
					'classes'   => array_values( array_filter( (array) $i->classes ) ),
				];
			}
		}
		return [ 'items' => $out ];
	}
}
