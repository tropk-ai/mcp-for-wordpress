<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
/**
 * Expose the per-widget atomic props schema. Lets the model author valid
 * V4 settings without guessing which keys exist or what envelope each
 * one expects.
 */
final class ElementorGetAtomicSchemaAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-atomic-schema'; }
	protected function meta(): array { return [
		'label'       => __( 'Get the V4 atomic-widget props schema', 'mcp-for-wordpress' ),
		'description' => __( "Returns the typed-prop schema (\$\$type keys, default values, settings, dependencies) for a given V4 widget/element type. Use this before authoring settings so you know which keys exist and which envelope each one expects. Pass widgetType (e.g. 'e-heading', 'e-button', 'e-div-block') OR pass list=true to get every registered atomic schema in one call.", 'mcp-for-wordpress' ),
		'readonly'    => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [
			'widgetType' => [ 'type' => 'string' ],
			'list'       => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'schemas' => [ 'type' => 'object' ],
			'count'   => [ 'type' => 'integer' ],
		],
	]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			throw new \RuntimeException( 'Elementor is not loaded.' );
		}
		$plugin = \Elementor\Plugin::$instance ?? null;
		if ( ! $plugin ) {
			throw new \RuntimeException( 'Elementor plugin instance is not available.' );
		}
		$requested = (string) ( $input['widgetType'] ?? '' );
		$list      = (bool)   ( $input['list'] ?? false );

		$candidates = [];
		// Widgets first (e-heading, e-button, …), then element types
		// (e-div-block, e-flexbox, e-grid).
		if ( ! empty( $plugin->widgets_manager ) ) {
			foreach ( (array) $plugin->widgets_manager->get_widget_types() as $name => $widget ) {
				$candidates[ (string) $name ] = $widget;
			}
		}
		if ( ! empty( $plugin->elements_manager ) ) {
			foreach ( (array) $plugin->elements_manager->get_element_types() as $name => $el ) {
				$candidates[ (string) $name ] = $el;
			}
		}

		$schemas = [];
		foreach ( $candidates as $name => $instance ) {
			if ( ! is_object( $instance ) ) continue;
			$class = get_class( $instance );
			if ( ! method_exists( $class, 'get_props_schema' ) ) continue;
			$is_atomic = ( str_starts_with( $name, 'e-' ) || str_starts_with( $name, 'a-' ) );
			if ( ! $is_atomic ) continue;
			if ( ! $list && '' !== $requested && $name !== $requested ) continue;
			try {
				$schema = $class::get_props_schema();
				$schemas[ $name ] = array_map(
					static fn ( $prop_type ) => is_object( $prop_type ) && method_exists( $prop_type, 'jsonSerialize' )
						? $prop_type->jsonSerialize()
						: $prop_type,
					(array) $schema
				);
			} catch ( \Throwable $e ) {
				$schemas[ $name ] = [ 'error' => $e->getMessage() ];
			}
		}
		if ( ! $list && '' !== $requested && empty( $schemas ) ) {
			throw new \RuntimeException( sprintf( 'No atomic widget registered with type "%s".', $requested ) );
		}
		return [ 'schemas' => $schemas, 'count' => count( $schemas ) ];
	}
}
