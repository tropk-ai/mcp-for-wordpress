<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorUpdateContainerAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-update-container'; }
	protected function meta(): array { return [
		'label' => __( 'Update an Elementor container', 'mcp-for-wordpress' ),
		'description' => __( 'Partially updates settings on an existing container element (flex/grid layout, background, padding, etc.). Rejects non-container targets.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'element_id', 'settings' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'element_id' => [ 'type' => 'string', 'minLength' => 1 ],
			'settings'   => [ 'type' => 'object' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'updated' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id      = (int) $input['post_id'];
		$elem_id = (string) $input['element_id'];
		$page    = ElementorPage::load( $id );
		$node    = $page->find_widget( $elem_id );
		if ( null === $node ) {
			throw new \RuntimeException( sprintf( 'Element "%s" not found.', $elem_id ) );
		}
		if ( 'container' !== ( $node['elType'] ?? '' ) ) {
			throw new \RuntimeException( 'Target element is not a container. Use elementor-update-widget for widgets.' );
		}
		$incoming = (array) $input['settings'];
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-update-container' );
		foreach ( $incoming as $k => $v ) {
			$page->update_widget_setting( $elem_id, (string) $k, $v );
		}
		$page->save();
		return [ 'updated' => true, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
