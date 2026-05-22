<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
final class ElementorAddWidgetAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-add-widget'; }
	protected function meta(): array { return [ 'label' => __( 'Add a widget into a container', 'mcp-for-wordpress' ), 'description' => __( "Appends a widget (heading / text-editor / button / image) into a container by container ID. Atomic V4 widget creation is intentionally blocked — use a template instead.", 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id', 'container_id', 'widget_type', 'settings' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'container_id' => [ 'type' => 'string' ], 'widget_type' => [ 'type' => 'string' ], 'settings' => [ 'type' => 'object' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'added' => [ 'type' => 'boolean' ], 'widget_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$type = (string) $input['widget_type'];
		if ( str_starts_with( $type, 'a-' ) || str_starts_with( $type, 'e-' ) ) {
			throw new \RuntimeException( 'Atomic widget creation is blocked (V4 schema is opaque). Use elementor-import-page from a template instead.' );
		}
		$id = (int) $input['post_id'];
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-add-widget' );
		$raw = get_post_meta( $id, '_elementor_data', true );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : [] );
		$new_id = bin2hex( random_bytes( 4 ) );
		$found = false;
		$walk = function ( array &$nodes ) use ( $input, $new_id, $type, &$walk, &$found ) {
			foreach ( $nodes as &$n ) {
				if ( ( $n['id'] ?? '' ) === (string) $input['container_id'] ) {
					if ( ! isset( $n['elements'] ) || ! is_array( $n['elements'] ) ) $n['elements'] = [];
					$n['elements'][] = [ 'id' => $new_id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => (array) $input['settings'], 'elements' => [] ];
					$found = true;
					return;
				}
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) ) {
					$walk( $n['elements'] );
					if ( $found ) return;
				}
			}
			unset( $n );
		};
		$walk( $data );
		if ( $found ) {
			update_post_meta( $id, '_elementor_data', wp_slash( (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
			delete_post_meta( $id, '_elementor_css' );
		}
		return [ 'added' => $found, 'widget_id' => $found ? $new_id : null ];
	}
}
