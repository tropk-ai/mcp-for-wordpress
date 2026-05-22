<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorResetNegativeMarginsSubtreeAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-reset-negative-margins-subtree'; }
	protected function meta(): array { return [
		'label'       => __( 'Reset negative margins (subtree)', 'mcp-for-wordpress' ),
		'description' => __( 'Clamps any negative margin values to zero on every element in a subtree.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'element_id' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'element_id' => [ 'type' => 'string', 'minLength' => 1 ],
			'dry_run'    => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'updated'       => [ 'type' => 'boolean' ],
			'changed_count' => [ 'type' => 'integer' ],
			'snapshot_id'   => [ 'type' => [ 'string', 'null' ] ],
		],
	]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		if ( ! ElementorPage::is_elementor_post( $id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $id ) );
		}
		$dry = (bool) ( $input['dry_run'] ?? false );
		$page = ElementorPage::load( $id );
		$root = $page->find_widget( (string) $input['element_id'] );
		if ( ! is_array( $root ) ) {
			throw new \RuntimeException( sprintf( 'Element %s not found.', (string) $input['element_id'] ) );
		}
		$updates = [];
		$collect = static function ( array $node ) use ( &$collect, &$updates ): void {
			$m = $node['settings']['margin'] ?? null;
			if ( is_array( $m ) ) {
				$dirty = false;
				$new = $m;
				foreach ( [ 'top', 'right', 'bottom', 'left' ] as $k ) {
					if ( isset( $new[ $k ] ) && (float) $new[ $k ] < 0 ) { $new[ $k ] = 0; $dirty = true; }
				}
				if ( $dirty ) { $updates[ (string) ( $node['id'] ?? '' ) ] = $new; }
			}
			foreach ( (array) ( $node['elements'] ?? [] ) as $c ) { if ( is_array( $c ) ) { $collect( $c ); } }
		};
		$collect( $root );
		$changed = 0;
		foreach ( $updates as $eid => $val ) {
			if ( '' !== $eid && $page->update_widget_setting( $eid, 'margin', $val ) ) { $changed++; }
		}
		$snap = null;
		if ( ! $dry && $changed > 0 ) {
			$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-reset-negative-margins-subtree' );
			$page->save();
		}
		return [ 'updated' => ! $dry && $changed > 0, 'changed_count' => $changed, 'snapshot_id' => $snap['snapshot_id'] ?? null ];
	}
}
