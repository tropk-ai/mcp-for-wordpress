<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorCopyLaneSettingsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-copy-lane-settings'; }
	protected function meta(): array { return [
		'label'       => __( 'Copy lane settings between containers', 'mcp-for-wordpress' ),
		'description' => __( 'Copies content_width, padding and flex_gap from a source container to one or more target containers.', 'mcp-for-wordpress' ),
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
			throw new \RuntimeException( sprintf( 'Source element %s not found.', (string) $input['source_id'] ) );
		}
		$src = (array) ( $source['settings'] ?? [] );
		$keys = [ 'content_width', 'width', 'padding', 'flex_gap', 'boxed_width' ];
		$changed = 0;
		foreach ( (array) $input['target_ids'] as $tid ) {
			$tid = (string) $tid;
			foreach ( $keys as $k ) {
				if ( array_key_exists( $k, $src ) ) {
					if ( $page->update_widget_setting( $tid, $k, $src[ $k ] ) ) { $changed++; }
				}
			}
		}
		$snap = null;
		if ( ! $dry && $changed > 0 ) {
			$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-copy-lane-settings' );
			$page->save();
		}
		return [ 'updated' => ! $dry && $changed > 0, 'changed_count' => $changed, 'snapshot_id' => $snap['snapshot_id'] ?? null ];
	}
}
