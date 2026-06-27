<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\AtomicProps;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorRemoveElementStyleAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-remove-element-style'; }
	protected function meta(): array { return [
		'label'       => __( 'Remove a local style from a V4 atomic element', 'mcp-for-wordpress' ),
		'description' => __( 'Deletes a local style by id from a V4 atomic element\'s `styles` map and unwires it from the element\'s `classes` prop. Snapshots first.', 'mcp-for-wordpress' ),
		'destructive' => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'element_id', 'style_id' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'element_id' => [ 'type' => 'string', 'minLength' => 1 ],
			'style_id'   => [ 'type' => 'string', 'minLength' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'removed'     => [ 'type' => 'boolean' ],
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
		$snap     = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-remove-element-style' );
		$page     = ElementorPage::load( $id );
		$target   = (string) $input['element_id'];
		$style_id = (string) $input['style_id'];
		$ok       = false;
		$page->walk_for_update( function ( array &$node ) use ( $target, $style_id, &$ok ): void {
			if ( $ok ) return;
			if ( (string) ( $node['id'] ?? '' ) !== $target ) return;
			$styles = (array) ( $node['styles'] ?? [] );
			if ( ! isset( $styles[ $style_id ] ) ) return;
			unset( $styles[ $style_id ] );
			$node['styles'] = $styles;

			// Unwire from classes prop too.
			$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
			if ( isset( $settings['classes'] ) && AtomicProps::is_envelope( $settings['classes'] ) ) {
				$current = (array) $settings['classes']['value'];
				$current = array_values( array_filter( $current, static fn ( $v ): bool => (string) $v !== $style_id ) );
				$settings['classes'] = AtomicProps::classes( $current );
				$node['settings']    = $settings;
			}
			$ok = true;
		} );
		if ( $ok ) $page->save();
		return [ 'removed' => $ok, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
