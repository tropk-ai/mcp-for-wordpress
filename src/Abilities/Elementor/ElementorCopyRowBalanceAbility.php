<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorCopyRowBalanceAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-copy-row-balance'; }
	protected function meta(): array { return [
		'label'       => __( 'Copy row column balance', 'mcp-for-wordpress' ),
		'description' => __( 'Copies the column-width distribution from a source row to one or more target rows so they share the same balance.', 'mcp-for-wordpress' ),
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
			throw new \RuntimeException( sprintf( 'Source row %s not found.', (string) $input['source_id'] ) );
		}
		$src_widths = [];
		foreach ( (array) ( $source['elements'] ?? [] ) as $col ) {
			if ( ! is_array( $col ) ) { continue; }
			$src_widths[] = $col['settings']['width'] ?? $col['settings']['_inline_size'] ?? null;
		}
		$changed = 0;
		foreach ( (array) $input['target_ids'] as $tid ) {
			$row = $page->find_widget( (string) $tid );
			if ( ! is_array( $row ) ) { continue; }
			$cols = array_values( array_filter( (array) ( $row['elements'] ?? [] ), 'is_array' ) );
			foreach ( $cols as $i => $col ) {
				if ( ! isset( $src_widths[ $i ] ) || null === $src_widths[ $i ] ) { continue; }
				if ( $page->update_widget_setting( (string) ( $col['id'] ?? '' ), 'width', $src_widths[ $i ] ) ) { $changed++; }
			}
		}
		$snap = null;
		if ( ! $dry && $changed > 0 ) {
			$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-copy-row-balance' );
			$page->save();
		}
		return [ 'updated' => ! $dry && $changed > 0, 'changed_count' => $changed, 'snapshot_id' => $snap['snapshot_id'] ?? null ];
	}
}
