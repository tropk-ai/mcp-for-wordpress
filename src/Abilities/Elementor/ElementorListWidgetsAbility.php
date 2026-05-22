<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorListWidgetsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-widgets'; }
	protected function meta(): array { return [
		'label' => __( 'List Elementor widgets', 'mcp-for-wordpress' ),
		'description' => __( 'Returns a flat list of every widget on the page with id, type, depth and a text snippet.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [
			'post_id'      => [ 'type' => 'integer', 'minimum' => 1 ],
			'widget_types' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'count' => [ 'type' => 'integer' ], 'widgets' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$widgets = ElementorPage::load( (int) $input['post_id'] )->widgets();
		if ( isset( $input['widget_types'] ) && is_array( $input['widget_types'] ) ) {
			$widgets = array_values( array_filter( $widgets, static fn( $w ) => in_array( $w['widgetType'], (array) $input['widget_types'], true ) ) );
		}
		return [ 'count' => count( $widgets ), 'widgets' => $widgets ];
	}
}
