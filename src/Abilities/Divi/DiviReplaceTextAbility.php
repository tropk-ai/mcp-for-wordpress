<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Divi;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Divi\DiviPage;

final class DiviReplaceTextAbility extends AbstractAbility {
	public function slug(): string { return 'divi-replace-text'; }
	protected function meta(): array { return [
		'label'       => __( 'Replace text in a Divi page', 'mcp-for-wordpress' ),
		'description' => __( 'Search-and-replace text inside Divi 5 module attributes and inner content. Optionally filter by module type. Supports dry-run mode. Snapshots before writing and flushes CSS after.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'find', 'replace' ],
		'properties'           => [
			'post_id'        => [ 'type' => 'integer', 'minimum' => 1 ],
			'find'           => [ 'type' => 'string', 'minLength' => 1 ],
			'replace'        => [ 'type' => 'string' ],
			'module_types'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
			'case_sensitive' => [ 'type' => 'boolean', 'default' => false ],
			'dry_run'        => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'updated'      => [ 'type' => 'boolean' ],
		'replacements' => [ 'type' => 'integer' ],
		'snapshot_id'  => [ 'type' => [ 'string', 'null' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) )
			&& current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$post_id = (int) $input['post_id'];
		if ( ! DiviPage::is_divi_post( $post_id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not a Divi builder page.', $post_id ) );
		}
		if ( ! DiviPage::is_divi5_post( $post_id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d uses Divi 4. Only Divi 5 pages are supported.', $post_id ) );
		}
		$dry_run = (bool) ( $input['dry_run'] ?? false );
		$mtypes  = isset( $input['module_types'] ) && is_array( $input['module_types'] )
			? array_map( 'strval', $input['module_types'] )
			: null;
		$cs = (bool) ( $input['case_sensitive'] ?? false );

		if ( $dry_run ) {
			$count = DiviPage::load( $post_id )->replace_text(
				(string) $input['find'],
				(string) ( $input['replace'] ?? '' ),
				$mtypes,
				$cs
			);
			return [ 'updated' => false, 'replacements' => $count, 'snapshot_id' => null ];
		}

		$snap  = ( new SnapshotManager() )->snapshot_post( $post_id, 'divi-replace-text' );
		$page  = DiviPage::load( $post_id );
		$count = $page->replace_text(
			(string) $input['find'],
			(string) ( $input['replace'] ?? '' ),
			$mtypes,
			$cs
		);
		if ( $count > 0 ) {
			$page->save();
		}
		return [
			'updated'      => $count > 0,
			'replacements' => $count,
			'snapshot_id'  => $snap['snapshot_id'],
		];
	}
}
