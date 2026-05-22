<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorGetElementSettingsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-element-settings'; }
	protected function meta(): array { return [
		'label' => __( 'Get Elementor element settings', 'mcp-for-wordpress' ),
		'description' => __( 'Returns the current settings object for a specific element on a page, plus its element type and widget type.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'element_id' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'element_id' => [ 'type' => 'string', 'minLength' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'element_id' => [ 'type' => 'string' ], 'elType' => [ 'type' => 'string' ],
		'widgetType' => [ 'type' => 'string' ], 'settings' => [ 'type' => 'object' ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$node = $page->find_widget( (string) $input['element_id'] );
		if ( null === $node ) {
			throw new \RuntimeException( sprintf( 'Element "%s" not found.', (string) $input['element_id'] ) );
		}
		return [
			'element_id' => (string) ( $node['id'] ?? '' ),
			'elType'     => (string) ( $node['elType'] ?? '' ),
			'widgetType' => (string) ( $node['widgetType'] ?? '' ),
			'settings'   => is_array( $node['settings'] ?? null ) ? $node['settings'] : [],
		];
	}
}
