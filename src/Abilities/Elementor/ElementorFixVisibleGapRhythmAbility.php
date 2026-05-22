<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorFixVisibleGapRhythmAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-fix-visible-gap-rhythm'; }
	protected function meta(): array { return [
		'label'       => __( 'Fix visible gap rhythm', 'mcp-for-wordpress' ),
		'description' => __( 'Sets a consistent flex_gap value on every row container so visible inter-element gaps share one rhythm.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [
			'post_id' => [ 'type' => 'integer', 'minimum' => 1 ],
			'gap'     => [ 'type' => 'integer', 'default' => 20 ],
			'dry_run' => [ 'type' => 'boolean', 'default' => false ],
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
		$gap = (int) ( $input['gap'] ?? 20 );
		$page = ElementorPage::load( $id );
		$targets = [];
		$walk = static function ( array $node ) use ( &$walk, &$targets ): void {
			if ( 'row' === strtolower( (string) ( $node['settings']['flex_direction'] ?? '' ) ) ) {
				$targets[] = (string) ( $node['id'] ?? '' );
			}
			foreach ( (array) ( $node['elements'] ?? [] ) as $c ) { if ( is_array( $c ) ) { $walk( $c ); } }
		};
		foreach ( $page->data() as $t ) { if ( is_array( $t ) ) { $walk( $t ); } }
		$changed = 0;
		foreach ( $targets as $tid ) {
			if ( '' !== $tid && $page->update_widget_setting( $tid, 'flex_gap', [ 'unit' => 'px', 'size' => $gap, 'column' => (string) $gap, 'row' => (string) $gap, 'isLinked' => true ] ) ) {
				$changed++;
			}
		}
		$snap = null;
		if ( ! $dry && $changed > 0 ) {
			$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-fix-visible-gap-rhythm' );
			$page->save();
		}
		return [ 'updated' => ! $dry && $changed > 0, 'changed_count' => $changed, 'snapshot_id' => $snap['snapshot_id'] ?? null ];
	}
}
