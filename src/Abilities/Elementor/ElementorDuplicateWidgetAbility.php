<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorDuplicateWidgetAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-duplicate-widget'; }
	protected function meta(): array { return [ 'label' => __( 'Duplicate an Elementor widget', 'mcp-for-wordpress' ), 'description' => __( "Clones a widget by ID, inserts the copy right after the original, and gives it a fresh ID.", 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id', 'widget_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'widget_id' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'duplicated' => [ 'type' => 'boolean' ], 'new_id' => [ 'type' => [ 'string', 'null' ] ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		$target = (string) $input['widget_id'];
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-duplicate-widget' );
		$raw = get_post_meta( $id, '_elementor_data', true );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : [] );
		$new_id = bin2hex( random_bytes( 4 ) );
		$found = false;
		$walk = function ( array &$nodes ) use ( $target, $new_id, &$walk, &$found ) {
			$i = 0;
			while ( $i < count( $nodes ) ) {
				if ( ( $nodes[ $i ]['id'] ?? '' ) === $target ) {
					$copy = $nodes[ $i ];
					$copy['id'] = $new_id;
					if ( isset( $copy['elements'] ) && is_array( $copy['elements'] ) ) {
						$regen = function( array &$kids ) use ( &$regen ) {
							foreach ( $kids as &$k ) { if ( is_array( $k ) ) { $k['id'] = bin2hex( random_bytes( 4 ) ); if ( isset( $k['elements'] ) && is_array( $k['elements'] ) ) $regen( $k['elements'] ); } }
							unset( $k );
						};
						$regen( $copy['elements'] );
					}
					array_splice( $nodes, $i + 1, 0, [ $copy ] );
					$found = true;
					return;
				}
				if ( isset( $nodes[ $i ]['elements'] ) && is_array( $nodes[ $i ]['elements'] ) ) {
					$walk( $nodes[ $i ]['elements'] );
					if ( $found ) return;
				}
				$i++;
			}
		};
		$walk( $data );
		if ( $found ) {
			update_post_meta( $id, '_elementor_data', wp_slash( (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
			delete_post_meta( $id, '_elementor_css' );
		}
		return [ 'duplicated' => $found, 'new_id' => $found ? $new_id : null, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
