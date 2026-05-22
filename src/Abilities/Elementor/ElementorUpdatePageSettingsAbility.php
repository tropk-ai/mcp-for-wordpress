<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
final class ElementorUpdatePageSettingsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-update-page-settings'; }
	protected function meta(): array { return [ 'label' => __( 'Update Elementor page settings', 'mcp-for-wordpress' ), 'description' => __( 'Merges new keys into _elementor_page_settings. Snapshots first.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id', 'patch' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'patch' => [ 'type' => 'object' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-update-page-settings' );
		$raw = get_post_meta( $id, '_elementor_page_settings', true );
		$cur = is_string( $raw ) ? (array) ( json_decode( $raw, true ) ?: [] ) : ( is_array( $raw ) ? $raw : [] );
		$merged = array_merge( $cur, (array) $input['patch'] );
		update_post_meta( $id, '_elementor_page_settings', wp_slash( (string) wp_json_encode( $merged, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
		delete_post_meta( $id, '_elementor_css' );
		return [ 'updated' => true, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
