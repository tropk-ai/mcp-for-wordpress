<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorEnforceBoundaryCoherenceAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-enforce-boundary-coherence'; }
	protected function meta(): array { return [
		'label'       => __( 'Enforce boundary coherence', 'mcp-for-wordpress' ),
		'description' => __( 'Resets stray border-radius / box-shadow settings on sections so the page boundaries read as one system.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [
			'post_id'       => [ 'type' => 'integer', 'minimum' => 1 ],
			'border_radius' => [ 'type' => 'integer', 'default' => 0 ],
			'dry_run'       => [ 'type' => 'boolean', 'default' => false ],
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
		$radius = (int) ( $input['border_radius'] ?? 0 );
		$page = ElementorPage::load( $id );
		$changed = 0;
		foreach ( $page->data() as $section ) {
			if ( ! is_array( $section ) ) { continue; }
			$sid = (string) ( $section['id'] ?? '' );
			if ( '' === $sid ) { continue; }
			$page->update_widget_setting( $sid, 'border_radius', [ 'unit' => 'px', 'top' => $radius, 'right' => $radius, 'bottom' => $radius, 'left' => $radius, 'isLinked' => true ] );
			$page->update_widget_setting( $sid, 'box_shadow_box_shadow_type', '' );
			$changed++;
		}
		$snap = null;
		if ( ! $dry && $changed > 0 ) {
			$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-enforce-boundary-coherence' );
			$page->save();
		}
		return [ 'updated' => ! $dry && $changed > 0, 'changed_count' => $changed, 'snapshot_id' => $snap['snapshot_id'] ?? null ];
	}
}
