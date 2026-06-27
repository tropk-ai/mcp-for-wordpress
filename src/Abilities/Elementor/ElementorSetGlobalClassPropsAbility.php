<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\AtomicProps;
use Tropk\Mcp\Elementor\ElementorRuntime;
/**
 * High-level shortcut: merge a single CSS-prop -> typed-value map into a
 * specific variant (breakpoint + state) of a V4 Global Class. Missing
 * variants are created. Keeps the rest of the variant array untouched.
 */
final class ElementorSetGlobalClassPropsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-set-global-class-props'; }
	protected function meta(): array { return [
		'label'       => __( 'Set CSS props on an Elementor V4 global class variant', 'mcp-for-wordpress' ),
		'description' => __( 'Merges a {<css-prop>: <typed value>} map into the chosen breakpoint/state variant of a V4 Global Class. Creates the variant if absent. Typed values may be JSON strings.', 'mcp-for-wordpress' ),
		'destructive' => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'id', 'props' ],
		'properties'           => [
			'id'         => [ 'type' => 'string', 'minLength' => 1 ],
			'breakpoint' => [ 'type' => [ 'string', 'null' ], 'default' => null ],
			'state'      => [ 'type' => [ 'string', 'null' ], 'default' => null ],
			'props'      => [ 'type' => 'object' ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'updated'      => [ 'type' => 'boolean' ],
			'variant_meta' => [ 'type' => 'object' ],
		],
	]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_theme_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$repo = ElementorRuntime::require_global_classes();
		$id   = (string) $input['id'];
		$cur  = $repo->get( $id );
		if ( ! is_array( $cur ) ) {
			throw new \RuntimeException( sprintf( 'Global class "%s" not found.', $id ) );
		}
		$breakpoint = array_key_exists( 'breakpoint', $input ) ? $input['breakpoint'] : null;
		$state      = array_key_exists( 'state', $input )      ? $input['state']      : null;
		$props      = (array) AtomicProps::normalize_value( (array) $input['props'] );

		$variants = (array) ( $cur['variants'] ?? [] );
		$found    = false;
		foreach ( $variants as $i => $variant ) {
			$vmeta = (array) ( $variant['meta'] ?? [] );
			if ( ( $vmeta['breakpoint'] ?? null ) === $breakpoint && ( $vmeta['state'] ?? null ) === $state ) {
				$variants[ $i ]['props'] = array_merge( (array) ( $variant['props'] ?? [] ), $props );
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			$variants[] = [
				'meta'  => [ 'breakpoint' => $breakpoint, 'state' => $state ],
				'props' => $props,
			];
		}
		$item    = [
			'id'       => $id,
			'type'     => (string) ( $cur['type'] ?? 'class' ),
			'label'    => (string) ( $cur['label'] ?? '' ),
			'variants' => array_values( $variants ),
		];
		$touched = [ $id => $item ];
		$changes = [ 'added' => [], 'modified' => [ $id ], 'deleted' => [], 'order' => false ];
		$repo->apply_changes( $touched, $changes, (array) $repo->get_order() );
		return [ 'updated' => true, 'variant_meta' => [ 'breakpoint' => $breakpoint, 'state' => $state ] ];
	}
}
