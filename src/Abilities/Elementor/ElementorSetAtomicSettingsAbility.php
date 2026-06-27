<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\AtomicProps;
use Tropk\Mcp\Elementor\ElementorPage;
/**
 * Merge typed-prop settings into a V4 atomic element. Each top-level
 * settings key is replaced (per-key, not deep-merged) so that envelopes
 * are atomic — partial typed-prop updates would break the $$type/value
 * shape Elementor expects.
 */
final class ElementorSetAtomicSettingsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-set-atomic-settings'; }
	protected function meta(): array { return [
		'label'       => __( 'Set V4 typed settings on an atomic element', 'mcp-for-wordpress' ),
		'description' => __( 'Merges a typed-prop settings patch into a V4 atomic element on the given post. Each top-level key replaces the existing value (envelopes are atomic). JSON-string envelopes are normalized to native objects. Snapshots the post first.', 'mcp-for-wordpress' ),
		'destructive' => true,
		'idempotent'  => true,
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
	protected function output_schema(): array { return [
		'properties' => [
			'updated'     => [ 'type' => 'boolean' ],
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
		$snap   = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-set-atomic-settings' );
		$page   = ElementorPage::load( $id );
		$patch  = (array) AtomicProps::normalize_value( (array) $input['settings'] );
		$target = (string) $input['element_id'];
		$ok     = false;
		$page->walk_for_update( function ( array &$node ) use ( $target, $patch, &$ok ): void {
			if ( $ok ) return;
			if ( (string) ( $node['id'] ?? '' ) !== $target ) return;
			$settings        = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
			$node['settings'] = array_merge( $settings, $patch );
			$ok               = true;
		} );
		if ( $ok ) $page->save();
		return [ 'updated' => $ok, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
