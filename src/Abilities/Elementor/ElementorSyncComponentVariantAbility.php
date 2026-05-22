<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorSyncComponentVariantAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-sync-component-variant'; }
	protected function meta(): array { return [
		'label'       => __( 'Sync component variant', 'mcp-for-wordpress' ),
		'description' => __( 'Copies settings (except text fields) from a source widget to target widgets of the same widget type so they share one visual variant.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'source_id', 'target_ids' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'source_id'  => [ 'type' => 'string', 'minLength' => 1 ],
			'target_ids' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
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
		$source = $page->find_widget( (string) $input['source_id'] );
		if ( ! is_array( $source ) ) {
			throw new \RuntimeException( sprintf( 'Source widget %s not found.', (string) $input['source_id'] ) );
		}
		$src_type = (string) ( $source['widgetType'] ?? '' );
		if ( ElementorPage::is_atomic_widget_type( $src_type ) ) {
			throw new \RuntimeException( 'Atomic widgets cannot be synced.' );
		}
		$text_fields = ElementorPage::resolve_text_fields_for( $src_type );
		$src_settings = (array) ( $source['settings'] ?? [] );
		foreach ( $text_fields as $f ) { unset( $src_settings[ $f ] ); }
		$changed = 0;
		foreach ( (array) $input['target_ids'] as $tid ) {
			$tid = (string) $tid;
			$node = $page->find_widget( $tid );
			if ( ! is_array( $node ) || ( $node['widgetType'] ?? '' ) !== $src_type ) { continue; }
			foreach ( $src_settings as $k => $v ) {
				if ( $page->update_widget_setting( $tid, (string) $k, $v ) ) { $changed++; }
			}
		}
		$snap = null;
		if ( ! $dry && $changed > 0 ) {
			$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-sync-component-variant' );
			$page->save();
		}
		return [ 'updated' => ! $dry && $changed > 0, 'changed_count' => $changed, 'snapshot_id' => $snap['snapshot_id'] ?? null ];
	}
}
