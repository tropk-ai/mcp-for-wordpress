<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorGetElementStylesAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-element-styles'; }
	protected function meta(): array { return [
		'label'       => __( 'Get a V4 atomic element\'s local styles', 'mcp-for-wordpress' ),
		'description' => __( 'Returns the `styles` map ({id -> {label, type, variants[{meta:{breakpoint?,state?}, props}]}}) of a V4 atomic element. These are the per-element CSS rules (distinct from Global Classes).', 'mcp-for-wordpress' ),
		'readonly'    => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'element_id' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'element_id' => [ 'type' => 'string', 'minLength' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'found'  => [ 'type' => 'boolean' ],
			'styles' => [ 'type' => 'object' ],
			'count'  => [ 'type' => 'integer' ],
		],
	]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		if ( ! ElementorPage::is_elementor_post( $id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $id ) );
		}
		$page    = ElementorPage::load( $id );
		$target  = (string) $input['element_id'];
		$out     = [ 'found' => false, 'styles' => [], 'count' => 0 ];
		$page->walk_for_update( function ( array &$node ) use ( $target, &$out ): void {
			if ( $out['found'] ) return;
			if ( (string) ( $node['id'] ?? '' ) !== $target ) return;
			$styles = (array) ( $node['styles'] ?? [] );
			$out    = [ 'found' => true, 'styles' => $styles, 'count' => count( $styles ) ];
		} );
		return $out;
	}
}
