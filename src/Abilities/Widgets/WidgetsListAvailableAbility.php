<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Widgets;

use Tropk\Mcp\Abilities\AbstractAbility;

final class WidgetsListAvailableAbility extends AbstractAbility {
	public function slug(): string { return 'widgets-list-available'; }
	protected function meta(): array { return [
		'label' => __( 'List available widgets', 'mcp-for-wordpress' ),
		'description' => __( 'Returns every classic WordPress widget registered globally.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'widgets' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_theme_options' ); }
	public function execute( array $input = [] ): array {
		global $wp_widget_factory;
		$out = [];
		if ( $wp_widget_factory && property_exists( $wp_widget_factory, 'widgets' ) ) {
			foreach ( (array) $wp_widget_factory->widgets as $cls => $w ) {
				$out[] = [
					'id_base'     => (string) ( $w->id_base ?? '' ),
					'name'        => (string) ( $w->name ?? '' ),
					'description' => (string) ( $w->widget_options['description'] ?? '' ),
				];
			}
		}
		return [ 'widgets' => $out ];
	}
}
