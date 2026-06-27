<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\AtomicProps;
use Tropk\Mcp\Elementor\ElementorPage;
/**
 * Upsert a local style on a V4 atomic element. Creates the style id if
 * absent (`e-<elementId>-<hash>`), merges the {breakpoint,state} variant
 * if it already exists, otherwise appends it. Also pushes the style id
 * onto the element's `classes` prop so the rendered CSS actually applies.
 */
final class ElementorSetElementStyleAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-set-element-style'; }
	protected function meta(): array { return [
		'label'       => __( 'Set a local style on a V4 atomic element', 'mcp-for-wordpress' ),
		'description' => __( 'Upserts a local style definition (a CSS class scoped to the element) and the requested breakpoint/state variant. Creates the style id on first call, merges props on subsequent calls. Auto-wires the class onto the element via the classes prop. Typed prop values may be JSON-string envelopes.', 'mcp-for-wordpress' ),
		'destructive' => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'element_id', 'props' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'element_id' => [ 'type' => 'string', 'minLength' => 1 ],
			'style_id'   => [ 'type' => 'string' ],
			'label'      => [ 'type' => 'string' ],
			'breakpoint' => [ 'type' => [ 'string', 'null' ], 'default' => null ],
			'state'      => [ 'type' => [ 'string', 'null' ], 'default' => null ],
			'props'      => [ 'type' => 'object' ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'updated'     => [ 'type' => 'boolean' ],
			'style_id'    => [ 'type' => 'string' ],
			'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
		],
	]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) )
			&& current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		if ( ! ElementorPage::is_elementor_post( $id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $id ) );
		}
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-set-element-style' );
		$page = ElementorPage::load( $id );

		$target     = (string) $input['element_id'];
		$style_in   = (string) ( $input['style_id'] ?? '' );
		$label      = (string) ( $input['label'] ?? '' );
		$breakpoint = array_key_exists( 'breakpoint', $input ) ? $input['breakpoint'] : null;
		$state      = array_key_exists( 'state', $input )      ? $input['state']      : null;
		$props      = (array) AtomicProps::normalize_value( (array) $input['props'] );
		$resolved   = '';
		$ok         = false;

		$page->walk_for_update( function ( array &$node ) use ( $target, &$ok, $style_in, $label, $breakpoint, $state, $props, &$resolved ): void {
			if ( $ok ) return;
			if ( (string) ( $node['id'] ?? '' ) !== $target ) return;
			$styles = (array) ( $node['styles'] ?? [] );
			// Pick the style id: explicit > existing matching label > new generated.
			$style_id = $style_in;
			if ( '' === $style_id && '' !== $label ) {
				foreach ( $styles as $sid => $def ) {
					if ( (string) ( $def['label'] ?? '' ) === $label ) {
						$style_id = (string) $sid;
						break;
					}
				}
			}
			if ( '' === $style_id ) {
				$style_id = 'e-' . $target . '-' . bin2hex( random_bytes( 4 ) );
			}
			$def = isset( $styles[ $style_id ] ) && is_array( $styles[ $style_id ] )
				? $styles[ $style_id ]
				: [ 'id' => $style_id, 'type' => 'class', 'label' => $label, 'variants' => [] ];
			$def['id']    = $style_id;
			$def['type']  = (string) ( $def['type'] ?? 'class' );
			if ( '' !== $label ) $def['label'] = $label;

			$variants = (array) ( $def['variants'] ?? [] );
			$matched  = false;
			foreach ( $variants as $i => $variant ) {
				$m = (array) ( $variant['meta'] ?? [] );
				if ( ( $m['breakpoint'] ?? null ) === $breakpoint && ( $m['state'] ?? null ) === $state ) {
					$variants[ $i ]['props'] = array_merge( (array) ( $variant['props'] ?? [] ), $props );
					$matched = true;
					break;
				}
			}
			if ( ! $matched ) {
				$variants[] = [ 'meta' => [ 'breakpoint' => $breakpoint, 'state' => $state ], 'props' => $props ];
			}
			$def['variants']      = array_values( $variants );
			$styles[ $style_id ]  = $def;
			$node['styles']       = $styles;

			// Wire the local style id into the element's classes prop so the
			// generated CSS selector `.<style_id>` actually matches.
			$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
			$current  = [];
			if ( isset( $settings['classes'] ) && AtomicProps::is_envelope( $settings['classes'] ) ) {
				$current = (array) $settings['classes']['value'];
			}
			if ( ! in_array( $style_id, $current, true ) ) {
				$current[] = $style_id;
			}
			$settings['classes'] = AtomicProps::classes( $current );
			$node['settings']    = $settings;

			$resolved = $style_id;
			$ok       = true;
		} );
		if ( $ok ) $page->save();
		return [ 'updated' => $ok, 'style_id' => $resolved, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
