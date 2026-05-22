<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorGetContainerSchemaAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-container-schema'; }
	protected function meta(): array { return [
		'label' => __( 'Get Elementor container schema', 'mcp-for-wordpress' ),
		'description' => __( 'Introspects Elementor\'s container element and returns a JSON Schema describing all its controls (layout, background, border, padding, grid, etc.).', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false, 'properties' => new \stdClass(),
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'schema' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			throw new \RuntimeException( 'Elementor is not active.' );
		}
		$el = \Elementor\Plugin::$instance ?? null;
		if ( ! $el || ! isset( $el->elements_manager ) ) {
			throw new \RuntimeException( 'Elementor elements manager unavailable.' );
		}
		$type = $el->elements_manager->get_element_types( 'container' );
		if ( ! $type ) {
			throw new \RuntimeException( 'Container element type not available.' );
		}
		$type_map = [
			'text' => 'string', 'textarea' => 'string', 'wysiwyg' => 'string', 'code' => 'string',
			'url' => 'object', 'media' => 'object', 'color' => 'string', 'select' => 'string',
			'select2' => 'string', 'choose' => 'string', 'font' => 'string', 'switcher' => 'string',
			'number' => 'number', 'slider' => 'object', 'dimensions' => 'object',
			'image_dimensions' => 'object', 'repeater' => 'array', 'gallery' => 'array',
			'icons' => 'object', 'icon' => 'string', 'hidden' => 'string', 'heading' => 'string',
			'raw_html' => 'string', 'popover_toggle' => 'string',
		];
		$schema = [ 'type' => 'object', 'description' => 'Settings for the Container element.', 'properties' => [] ];
		foreach ( (array) $type->get_controls() as $cid => $control ) {
			$ct  = (string) ( $control['type'] ?? 'text' );
			$prop = [ 'type' => $type_map[ $ct ] ?? 'string' ];
			if ( ! empty( $control['label'] ) )   $prop['description'] = (string) $control['label'];
			if ( isset( $control['default'] ) )   $prop['default'] = $control['default'];
			if ( ! empty( $control['options'] ) && is_array( $control['options'] ) ) {
				$enum = array_values( array_filter( array_keys( $control['options'] ), static fn( $k ) => '' !== $k ) );
				if ( ! empty( $enum ) ) $prop['enum'] = $enum;
			}
			$schema['properties'][ (string) $cid ] = $prop;
		}
		return [ 'schema' => $schema ];
	}
}
