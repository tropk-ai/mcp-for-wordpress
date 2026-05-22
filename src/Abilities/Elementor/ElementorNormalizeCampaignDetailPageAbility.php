<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorNormalizeCampaignDetailPageAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-normalize-campaign-detail-page'; }
	protected function meta(): array { return [
		'label'       => __( 'Normalize Elementor campaign-detail page', 'mcp-for-wordpress' ),
		'description' => __( 'Applies the standard campaign-detail layout recipe (boxed lane width, zero hidden gutters, row rhythm) to the given page. Snapshots first.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'lane_width' => [ 'type' => 'integer', 'default' => 1140 ],
			'rhythm_gap' => [ 'type' => 'integer', 'default' => 18 ],
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
		$lane = (int) ( $input['lane_width'] ?? 1140 );
		$gap  = (int) ( $input['rhythm_gap'] ?? 18 );
		$dry  = (bool) ( $input['dry_run'] ?? false );
		$page = ElementorPage::load( $id );
		$changed = 0;
		foreach ( $page->data() as $top ) {
			if ( ! is_array( $top ) ) { continue; }
			$page->update_widget_setting( (string) ( $top['id'] ?? '' ), 'content_width', [ 'unit' => 'px', 'size' => $lane ] );
			$page->update_widget_setting( (string) ( $top['id'] ?? '' ), 'flex_gap', [ 'unit' => 'px', 'size' => $gap ] );
			$changed++;
		}
		$snap = null;
		if ( ! $dry && $changed > 0 ) {
			$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-normalize-campaign-detail-page' );
			$page->save();
		}
		return [ 'updated' => ! $dry && $changed > 0, 'changed_count' => $changed, 'snapshot_id' => $snap['snapshot_id'] ?? null ];
	}
}
