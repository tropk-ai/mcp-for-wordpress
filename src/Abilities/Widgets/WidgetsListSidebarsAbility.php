<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Widgets;

use Tropk\Mcp\Abilities\AbstractAbility;

final class WidgetsListSidebarsAbility extends AbstractAbility {
	public function slug(): string { return 'widgets-list-sidebars'; }
	protected function meta(): array { return [
		'label' => __( 'List sidebars', 'mcp-for-wordpress' ),
		'description' => __( 'Lists every sidebar / widget area declared by the active theme.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'sidebars' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_theme_options' ); }
	public function execute( array $input = [] ): array {
		global $wp_registered_sidebars;
		$out = [];
		foreach ( (array) $wp_registered_sidebars as $id => $s ) {
			$out[] = [
				'id'          => (string) $id,
				'name'        => (string) ( $s['name'] ?? '' ),
				'description' => (string) ( $s['description'] ?? '' ),
			];
		}
		return [ 'sidebars' => $out ];
	}
}
