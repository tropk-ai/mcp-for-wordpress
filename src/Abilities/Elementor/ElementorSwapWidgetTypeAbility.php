<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorSwapWidgetTypeAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-swap-widget-type'; }
	protected function meta(): array { return [ 'label' => __( "Swap an Elementor widget's type", 'mcp-for-wordpress' ), 'description' => __( "Changes widgetType on a widget by ID (e.g. text-editor → heading). Settings are kept as-is, so check after with a get-widget call.", 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id', 'widget_id', 'new_type' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'widget_id' => [ 'type' => 'string' ], 'new_type' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$new = (string) $input['new_type'];
		if ( str_starts_with( $new, 'a-' ) || str_starts_with( $new, 'e-' ) ) {
			throw new \RuntimeException( 'Swapping to an atomic widget type is blocked (opaque schema).' );
		}
		$id = (int) $input['post_id'];
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-swap-widget-type' );
		$raw = get_post_meta( $id, '_elementor_data', true );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : [] );
		$ok = false;
		$walk = function ( array &$nodes ) use ( &$walk, $input, $new, &$ok ) {
			foreach ( $nodes as &$n ) {
				if ( ( $n['id'] ?? '' ) === (string) $input['widget_id'] ) { $n['widgetType'] = $new; $ok = true; return; }
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) ) { $walk( $n['elements'] ); if ( $ok ) return; }
			}
			unset( $n );
		};
		$walk( $data );
		if ( $ok ) {
			update_post_meta( $id, '_elementor_data', wp_slash( (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
			delete_post_meta( $id, '_elementor_css' );
		}
		return [ 'updated' => $ok, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
