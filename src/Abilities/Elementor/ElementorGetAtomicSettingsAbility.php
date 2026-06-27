<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
/**
 * Read a V4 atomic element's `settings` map and its local `styles` map.
 * Lifts the previous "atomic widgets are opaque" treatment for reads —
 * the caller still gets exactly the raw typed-prop structure Elementor
 * persists, so what comes out can be fed back into update-widget /
 * update-widget-setting verbatim.
 */
final class ElementorGetAtomicSettingsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-atomic-settings'; }
	protected function meta(): array { return [
		'label'       => __( 'Get a V4 atomic element\'s settings and local styles', 'mcp-for-wordpress' ),
		'description' => __( 'Returns the typed `settings` map and the local `styles` map (id -> {label, type, variants[]}) of a V4 atomic element on the given post. Pairs with get-atomic-schema for authoring.', 'mcp-for-wordpress' ),
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
			'found'      => [ 'type' => 'boolean' ],
			'widgetType' => [ 'type' => [ 'string', 'null' ] ],
			'elType'     => [ 'type' => [ 'string', 'null' ] ],
			'settings'   => [ 'type' => 'object' ],
			'styles'     => [ 'type' => 'object' ],
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
		$result  = [ 'found' => false, 'widgetType' => null, 'elType' => null, 'settings' => [], 'styles' => [] ];
		$page->walk_for_update( function ( array &$node ) use ( $target, &$result ): void {
			if ( $result['found'] ) return;
			if ( (string) ( $node['id'] ?? '' ) !== $target ) return;
			$result = [
				'found'      => true,
				'widgetType' => isset( $node['widgetType'] ) ? (string) $node['widgetType'] : null,
				'elType'     => isset( $node['elType'] )     ? (string) $node['elType']     : null,
				'settings'   => (array) ( $node['settings'] ?? [] ),
				'styles'     => (array) ( $node['styles']   ?? [] ),
			];
		} );
		return $result;
	}
}
