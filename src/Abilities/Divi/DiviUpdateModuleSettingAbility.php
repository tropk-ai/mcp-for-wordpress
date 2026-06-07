<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Divi;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Divi\DiviPage;

final class DiviUpdateModuleSettingAbility extends AbstractAbility {
	public function slug(): string { return 'divi-update-module-setting'; }
	protected function meta(): array { return [
		'label'       => __( 'Update a Divi module setting', 'mcp-for-wordpress' ),
		'description' => __( 'Updates a single shortcode attribute on a Divi 5 module identified by its ID. Snapshots the post first and flushes the Divi CSS cache afterwards.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'module_id', 'attr_key', 'attr_value' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'module_id'  => [ 'type' => 'string', 'minLength' => 1 ],
			'attr_key'   => [ 'type' => 'string', 'minLength' => 1 ],
			'attr_value' => [ 'type' => 'string' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'updated'     => [ 'type' => 'boolean' ],
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
		$page = DiviPage::load( $post_id );
		$node = $page->find_module( (string) $input['module_id'] );
		if ( null === $node ) {
			throw new \RuntimeException( sprintf( 'Module "%s" not found on post %d.', $input['module_id'], $post_id ) );
		}
		$snap    = ( new SnapshotManager() )->snapshot_post( $post_id, 'divi-update-module-setting' );
		$updated = $page->update_module_attr(
			(string) $input['module_id'],
			(string) $input['attr_key'],
			(string) $input['attr_value']
		);
		if ( $updated ) {
			$page->save();
		}
		return [
			'updated'     => $updated,
			'snapshot_id' => $snap['snapshot_id'],
		];
	}
}
