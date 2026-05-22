<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Pages;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
final class PagesUpdateAbility extends AbstractAbility {
	public function slug(): string { return 'pages-update'; }
	protected function meta(): array { return [ 'label' => __( 'Update a page', 'mcp-for-wordpress' ), 'description' => __( 'Patches an existing page. Snapshots before writing.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'id' ], 'properties' => [ 'id' => [ 'type' => 'integer' ], 'patch' => [ 'type' => 'object' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$snap = ( new SnapshotManager() )->snapshot_post( (int) $input['id'], 'pages-update' );
		$args = [ 'ID' => (int) $input['id'] ];
		foreach ( (array) ( $input['patch'] ?? [] ) as $k => $v ) {
			if ( in_array( $k, [ 'title' => 1, 'content' => 1, 'status' => 1, 'parent' => 1, 'menu_order' => 1 ] ? array_keys([ 'title' => 1, 'content' => 1, 'status' => 1, 'parent' => 1, 'menu_order' => 1 ]) : [], true ) ) {
				$map = [ 'title' => 'post_title', 'content' => 'post_content', 'status' => 'post_status', 'parent' => 'post_parent', 'menu_order' => 'menu_order' ];
				if ( isset( $map[ $k ] ) ) $args[ $map[ $k ] ] = $v;
			}
		}
		wp_update_post( $args, true );
		return [ 'updated' => true, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
