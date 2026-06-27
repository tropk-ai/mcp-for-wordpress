<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
/**
 * Enumerate every registered V4 atomic prop-type ($$type catalog). Pulled
 * from Style_Schema + every atomic widget's get_props_schema(), so it
 * automatically picks up Pro additions (display-conditions, query
 * filters) without needing per-version maintenance.
 */
final class ElementorListPropTypesAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-prop-types'; }
	protected function meta(): array { return [
		'label'       => __( 'List V4 atomic prop-types ($$type catalog)', 'mcp-for-wordpress' ),
		'description' => __( 'Returns every typed-prop key currently registered on this site (string, size, dimensions, classes, link, image, background, …) with its base kind (plain/object/array/union). Use this as the catalog of valid $$type values when authoring settings or style props.', 'mcp-for-wordpress' ),
		'readonly'    => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => new \stdClass(),
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'prop_types' => [ 'type' => 'array' ],
			'count'      => [ 'type' => 'integer' ],
		],
	]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			throw new \RuntimeException( 'Elementor is not loaded.' );
		}

		$seen = [];
		$harvest = function ( $prop_type ) use ( &$harvest, &$seen ): void {
			if ( ! is_object( $prop_type ) ) return;
			$key  = method_exists( $prop_type, 'get_key' )  ? (string) $prop_type::get_key()  : '';
			$kind = method_exists( $prop_type, 'get_kind' ) ? (string) $prop_type->get_kind() : '';
			if ( '' !== $key && ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = [ 'key' => $key, 'kind' => $kind ];
			}
			if ( method_exists( $prop_type, 'jsonSerialize' ) ) {
				$ser = $prop_type->jsonSerialize();
				if ( is_array( $ser ) ) {
					if ( isset( $ser['shape'] ) && is_array( $ser['shape'] ) ) {
						foreach ( $ser['shape'] as $inner ) { $harvest( $inner ); }
					}
					if ( isset( $ser['item_prop_type'] ) ) {
						$harvest( $ser['item_prop_type'] );
					}
					if ( isset( $ser['prop_types'] ) && is_array( $ser['prop_types'] ) ) {
						foreach ( $ser['prop_types'] as $inner ) { $harvest( $inner ); }
					}
				}
			}
		};

		if ( class_exists( '\\Elementor\\Modules\\AtomicWidgets\\Styles\\Style_Schema' ) ) {
			foreach ( (array) \Elementor\Modules\AtomicWidgets\Styles\Style_Schema::get() as $prop_type ) {
				$harvest( $prop_type );
			}
		}
		$plugin = \Elementor\Plugin::$instance ?? null;
		if ( $plugin ) {
			foreach ( [ 'widgets_manager' => 'get_widget_types', 'elements_manager' => 'get_element_types' ] as $prop => $getter ) {
				if ( empty( $plugin->$prop ) ) continue;
				foreach ( (array) $plugin->$prop->$getter() as $name => $instance ) {
					if ( ! is_object( $instance ) ) continue;
					if ( ! str_starts_with( (string) $name, 'e-' ) && ! str_starts_with( (string) $name, 'a-' ) ) continue;
					$class = get_class( $instance );
					if ( ! method_exists( $class, 'get_props_schema' ) ) continue;
					foreach ( (array) $class::get_props_schema() as $prop_type ) {
						$harvest( $prop_type );
					}
				}
			}
		}

		$out = array_values( $seen );
		usort( $out, static fn ( $a, $b ): int => strcmp( $a['key'], $b['key'] ) );
		return [ 'prop_types' => $out, 'count' => count( $out ) ];
	}
}
