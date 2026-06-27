<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
/**
 * Exposes the V4 style schema (every CSS property an atomic style variant
 * can carry, with its prop-type), plus the valid breakpoints and states.
 * Without this the model is guessing which CSS keys are valid V4-side.
 */
final class ElementorGetStyleSchemaAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-style-schema'; }
	protected function meta(): array { return [
		'label'       => __( 'Get the V4 style schema (CSS props + states + breakpoints)', 'mcp-for-wordpress' ),
		'description' => __( 'Returns the typed schema of every CSS property an atomic style variant can carry (margin, padding, color, font-size, background, transform, …), plus the valid pseudo/class states (hover, focus, focus-visible, active, checked, e--selected) and the active breakpoints map.', 'mcp-for-wordpress' ),
		'readonly'    => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => new \stdClass(),
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'css_props'   => [ 'type' => 'object' ],
			'states'      => [ 'type' => 'array' ],
			'breakpoints' => [ 'type' => 'object' ],
		],
	]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		if ( ! class_exists( '\\Elementor\\Modules\\AtomicWidgets\\Styles\\Style_Schema' ) ) {
			throw new \RuntimeException( 'Elementor V4 atomic widgets are not loaded on this site.' );
		}
		$schema    = \Elementor\Modules\AtomicWidgets\Styles\Style_Schema::get();
		$css_props = [];
		foreach ( (array) $schema as $key => $prop_type ) {
			$css_props[ (string) $key ] = is_object( $prop_type ) && method_exists( $prop_type, 'jsonSerialize' )
				? $prop_type->jsonSerialize()
				: $prop_type;
		}
		$states = class_exists( '\\Elementor\\Modules\\AtomicWidgets\\Styles\\Style_States' )
			? (array) \Elementor\Modules\AtomicWidgets\Styles\Style_States::get_valid_states()
			: [];
		// Breakpoints come from the core Breakpoints_Manager.
		$breakpoints = [];
		if ( ! empty( \Elementor\Plugin::$instance->breakpoints ) ) {
			$bm = \Elementor\Plugin::$instance->breakpoints;
			if ( method_exists( $bm, 'get_active_breakpoints' ) ) {
				foreach ( (array) $bm->get_active_breakpoints() as $name => $bp ) {
					$breakpoints[ (string) $name ] = [
						'label'     => method_exists( $bp, 'get_label' )     ? (string) $bp->get_label() : '',
						'direction' => method_exists( $bp, 'get_direction' ) ? (string) $bp->get_direction() : '',
						'value'     => method_exists( $bp, 'get_value' )     ? (int)    $bp->get_value() : 0,
					];
				}
			}
		}
		return [ 'css_props' => $css_props, 'states' => array_values( $states ), 'breakpoints' => $breakpoints ];
	}
}
