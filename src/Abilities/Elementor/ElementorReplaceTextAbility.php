<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorReplaceTextAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-replace-text'; }
	protected function meta(): array { return [
		'label' => __( 'Replace text in an Elementor page', 'mcp-for-wordpress' ),
		'description' => __( 'Search-and-replace text inside classic Elementor widgets. Atomic widgets (V4) are skipped. Snapshots the post first and flushes CSS afterwards.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'find', 'replace' ],
		'properties'           => [
			'post_id'        => [ 'type' => 'integer', 'minimum' => 1 ],
			'find'           => [ 'type' => 'string', 'minLength' => 1 ],
			'replace'        => [ 'type' => 'string' ],
			'widget_types'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
			'case_sensitive' => [ 'type' => 'boolean', 'default' => false ],
			'dry_run'        => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'replacements' => [ 'type' => 'integer' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		if ( ! ElementorPage::is_elementor_post( $id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $id ) );
		}
		$dry_run = (bool) ( $input['dry_run'] ?? false );
		$wtypes  = isset( $input['widget_types'] ) && is_array( $input['widget_types'] ) ? array_map( 'strval', $input['widget_types'] ) : null;
		if ( $dry_run ) {
			$count = ElementorPage::load( $id )->replace_text( (string) $input['find'], (string) ( $input['replace'] ?? '' ), $wtypes, (bool) ( $input['case_sensitive'] ?? false ) );
			return [ 'updated' => false, 'replacements' => $count, 'snapshot_id' => null ];
		}
		$snap  = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-replace-text' );
		$page  = ElementorPage::load( $id );
		$count = $page->replace_text( (string) $input['find'], (string) ( $input['replace'] ?? '' ), $wtypes, (bool) ( $input['case_sensitive'] ?? false ) );
		if ( $count > 0 ) $page->save();
		return [ 'updated' => $count > 0, 'replacements' => $count, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
