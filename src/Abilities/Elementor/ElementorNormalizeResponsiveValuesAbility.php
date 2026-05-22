<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorNormalizeResponsiveValuesAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-normalize-responsive-values'; }
	protected function meta(): array { return [
		'label'       => __( 'Normalize Elementor responsive values', 'mcp-for-wordpress' ),
		'description' => __( 'Fills missing tablet/mobile spacing values from desktop values and caps inherited horizontal spacing on narrower breakpoints.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [
			'post_id'                       => [ 'type' => 'integer', 'minimum' => 1 ],
			'tablet_horizontal_spacing_cap' => [ 'type' => 'number', 'default' => 40 ],
			'mobile_horizontal_spacing_cap' => [ 'type' => 'number', 'default' => 24 ],
			'dry_run'                       => [ 'type' => 'boolean', 'default' => false ],
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
		$t_cap = (float) ( $input['tablet_horizontal_spacing_cap'] ?? 40 );
		$m_cap = (float) ( $input['mobile_horizontal_spacing_cap'] ?? 24 );
		$page = ElementorPage::load( $id );
		$changed = 0;
		foreach ( $page->widgets() as $w ) {
			$wid = (string) ( $w['id'] ?? '' );
			$node = $page->find_widget( $wid );
			if ( ! is_array( $node ) || empty( $node['settings']['padding'] ) ) { continue; }
			$desktop = $node['settings']['padding'];
			$right = (float) ( $desktop['right'] ?? 0 );
			$left  = (float) ( $desktop['left'] ?? 0 );
			if ( ! isset( $node['settings']['padding_tablet'] ) && ( $left > $t_cap || $right > $t_cap ) ) {
				$tab = $desktop;
				$tab['left']  = min( $left, $t_cap );
				$tab['right'] = min( $right, $t_cap );
				$page->update_widget_setting( $wid, 'padding_tablet', $tab );
				$changed++;
			}
			if ( ! isset( $node['settings']['padding_mobile'] ) && ( $left > $m_cap || $right > $m_cap ) ) {
				$mob = $desktop;
				$mob['left']  = min( $left, $m_cap );
				$mob['right'] = min( $right, $m_cap );
				$page->update_widget_setting( $wid, 'padding_mobile', $mob );
				$changed++;
			}
		}
		$snap = null;
		if ( ! $dry && $changed > 0 ) {
			$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-normalize-responsive-values' );
			$page->save();
		}
		return [ 'updated' => ! $dry && $changed > 0, 'changed_count' => $changed, 'snapshot_id' => $snap['snapshot_id'] ?? null ];
	}
}
