<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Divi;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Divi\DiviPage;

final class DiviDeleteModuleAbility extends AbstractAbility {
	public function slug(): string { return 'divi-delete-module'; }
	protected function meta(): array { return [
		'label'       => __( 'Delete a Divi module', 'mcp-for-wordpress' ),
		'description' => __( 'Removes a Divi 5 module (or section/row/column) from a page by its ID. Snapshots the post first and flushes the CSS cache afterwards.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'module_id' ],
		'properties'           => [
			'post_id'   => [ 'type' => 'integer', 'minimum' => 1 ],
			'module_id' => [ 'type' => 'string', 'minLength' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'deleted'     => [ 'type' => 'boolean' ],
		'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
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
		$snap    = ( new SnapshotManager() )->snapshot_post( $post_id, 'divi-delete-module' );
		$page    = DiviPage::load( $post_id );
		$deleted = $page->delete_module( (string) $input['module_id'] );
		if ( $deleted ) {
			$page->save();
		}
		return [
			'deleted'     => $deleted,
			'snapshot_id' => $snap['snapshot_id'],
		];
	}
}
