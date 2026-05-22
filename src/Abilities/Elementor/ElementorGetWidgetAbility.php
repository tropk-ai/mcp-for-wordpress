<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorGetWidgetAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-widget'; }
	protected function meta(): array { return [
		'label' => __( 'Get an Elementor widget', 'mcp-for-wordpress' ),
		'description' => __( 'Returns the raw node (id, widgetType, settings, elements) for a single widget by ID. Returns the atomic widget JSON verbatim when the type is V4.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'widget_id' ],
		'properties'           => [
			'post_id'   => [ 'type' => 'integer', 'minimum' => 1 ],
			'widget_id' => [ 'type' => 'string', 'minLength' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'found' => [ 'type' => 'boolean' ], 'node' => [ 'type' => [ 'object', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$node = $page->find_widget( (string) $input['widget_id'] );
		return [ 'found' => null !== $node, 'node' => $node ];
	}
}
