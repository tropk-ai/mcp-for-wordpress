<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Menus;

use Tropk\Mcp\Abilities\AbstractAbility;

final class MenusAddItemAbility extends AbstractAbility {
	public function slug(): string { return 'menus-add-item'; }
	protected function meta(): array { return [
		'label' => __( 'Add a menu item', 'mcp-for-wordpress' ),
		'description' => __( 'Inserts a new item into a menu — page, post, custom URL, taxonomy or post type archive.', 'mcp-for-wordpress' ),
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'menu_id', 'title' ],
		'properties'           => [
			'menu_id'      => [ 'type' => 'integer', 'minimum' => 1 ],
			'title'        => [ 'type' => 'string', 'minLength' => 1 ],
			'url'          => [ 'type' => 'string' ],
			'type'         => [ 'type' => 'string', 'enum' => [ 'custom', 'post_type', 'taxonomy', 'post_type_archive' ], 'default' => 'custom' ],
			'object'       => [ 'type' => 'string' ],
			'object_id'    => [ 'type' => 'integer' ],
			'parent_id'    => [ 'type' => 'integer', 'minimum' => 0 ],
			'menu_order'   => [ 'type' => 'integer' ],
			'open_in_new_tab' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'added' => [ 'type' => 'boolean' ], 'item_id' => [ 'type' => [ 'integer', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_theme_options' ); }
	public function execute( array $input = [] ): array {
		$args = [
			'menu-item-title'     => (string) $input['title'],
			'menu-item-url'       => (string) ( $input['url'] ?? '' ),
			'menu-item-type'      => (string) ( $input['type'] ?? 'custom' ),
			'menu-item-object'    => (string) ( $input['object'] ?? '' ),
			'menu-item-object-id' => (int) ( $input['object_id'] ?? 0 ),
			'menu-item-parent-id' => (int) ( $input['parent_id'] ?? 0 ),
			'menu-item-position'  => (int) ( $input['menu_order'] ?? 0 ),
			'menu-item-status'    => 'publish',
		];
		if ( ! empty( $input['open_in_new_tab'] ) ) {
			$args['menu-item-target'] = '_blank';
		}
		$id = wp_update_nav_menu_item( (int) $input['menu_id'], 0, $args );
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message() );
		}
		return [ 'added' => true, 'item_id' => (int) $id ];
	}
}
