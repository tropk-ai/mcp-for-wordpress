<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorApplyTextHierarchyAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-apply-text-hierarchy'; }
	protected function meta(): array { return [ 'label' => __( 'Apply text hierarchy rules', 'mcp-for-wordpress' ), 'description' => __( "Walks heading widgets and demotes duplicate H1s to H2 (keeping the first). Useful before publish to fix accessibility/SEO heading order.", 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'demoted' => [ 'type' => 'integer' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-apply-text-hierarchy' );
		$page = ElementorPage::load( $id );
		$first = true; $demoted = 0;
		foreach ( $page->widgets() as $w ) {
			if ( "heading" !== ( $w['widgetType'] ?? '' ) ) continue;
			$node = $page->find_widget( (string) $w['id'] );
			if ( ! $node ) continue;
			$level = strtolower( (string) ( $node['settings']['header_size'] ?? 'h2' ) );
			if ( 'h1' !== $level ) { $first = $first || false; continue; }
			if ( $first ) { $first = false; continue; }
			$page->update_widget_setting( (string) $w['id'], 'header_size', 'h2' );
			$demoted++;
		}
		if ( $demoted > 0 ) $page->save();
		return [ 'demoted' => $demoted, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
