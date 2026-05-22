<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorRegeneratePageIdsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-regenerate-page-ids'; }
	protected function meta(): array { return [ 'label' => __( 'Regenerate every element ID on a page', 'mcp-for-wordpress' ), 'description' => __( 'Issues a fresh 8-hex ID for every container/section/column/widget. Useful after a deep copy where IDs collided. Atomic widget JSON is preserved verbatim (id only changes).', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-regenerate-page-ids' );
		$raw = get_post_meta( $id, '_elementor_data', true );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : [] );
		if ( ! is_array( $data ) ) return [ 'updated' => false, 'snapshot_id' => $snap['snapshot_id'] ];
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
		delete_post_meta( $id, '_elementor_css' );
		return [ 'updated' => true, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
