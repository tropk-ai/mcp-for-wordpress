<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Menus;

use Tropk\Mcp\Abilities\AbstractAbility;

final class MenusListAbility extends AbstractAbility {
	public function slug(): string { return 'menus-list'; }
	protected function meta(): array { return [
		'label' => __( 'List menus', 'mcp-for-wordpress' ),
		'description' => __( 'Lists all navigation menus on the site.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'menus' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_theme_options' ); }
	public function execute( array $input = [] ): array {
		$out = [];
		foreach ( wp_get_nav_menus() as $m ) {
			$out[] = [
				'id'    => (int) $m->term_id,
				'name'  => (string) $m->name,
				'slug'  => (string) $m->slug,
				'count' => (int) $m->count,
			];
		}
		return [ 'menus' => $out ];
	}
}
