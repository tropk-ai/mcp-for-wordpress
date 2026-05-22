<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
final class ElementorImportPageAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-import-page'; }
	protected function meta(): array { return [ 'label' => __( 'Import Elementor JSON into a post', 'mcp-for-wordpress' ), 'description' => __( "Overwrites a target post's _elementor_data with the supplied JSON. Snapshots the post first. All IDs are regenerated to avoid collisions.", 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id', 'data' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'data' => [ 'type' => 'array' ], 'page_settings' => [ 'type' => 'object' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'imported' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-import-page' );
		$data = (array) $input['data'];
		$regen = function( array &$nodes ) use ( &$regen ) {
			foreach ( $nodes as &$n ) {
				if ( ! is_array( $n ) ) continue;
				$n['id'] = bin2hex( random_bytes( 4 ) );
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) ) $regen( $n['elements'] );
			}
			unset( $n );
		};
		$regen( $data );
		update_post_meta( $id, '_elementor_data', wp_slash( (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		if ( isset( $input['page_settings'] ) && is_array( $input['page_settings'] ) ) {
			update_post_meta( $id, '_elementor_page_settings', wp_slash( (string) wp_json_encode( $input['page_settings'] ) ) );
		}
		delete_post_meta( $id, '_elementor_css' );
		return [ 'imported' => true, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
