<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorMeta;
final class ElementorUpdatePageSettingsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-update-page-settings'; }
	protected function meta(): array { return [ 'label' => __( 'Update Elementor page settings', 'mcp-for-wordpress' ), 'description' => __( 'Merges new keys into _elementor_page_settings. Snapshots first.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id', 'patch' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'patch' => [ 'type' => 'object' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-update-page-settings' );
		$cur = ElementorMeta::read_page_settings( $id );
		$merged = array_merge( $cur, (array) $input['patch'] );
		// Persist as a NATIVE array (not a JSON string): Elementor's
		// get_saved_settings() expects an array and 500s on a string.
		ElementorMeta::write_page_settings( $id, $merged );
		return [ 'updated' => true, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
