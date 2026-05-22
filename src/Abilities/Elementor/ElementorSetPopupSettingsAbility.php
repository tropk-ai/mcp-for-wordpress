<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorSetPopupSettingsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-set-popup-settings'; }
	protected function meta(): array { return [ 'label' => __( 'Set Elementor Pro popup settings', 'mcp-for-wordpress' ), 'description' => __( 'Writes triggers, timing, and display conditions for an Elementor Pro popup template. Each provided key replaces only that meta; missing keys are left untouched.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [
		'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
		'triggers'   => [ 'type' => 'object' ],
		'conditions' => [ 'type' => 'array' ],
		'timing'     => [ 'type' => 'object' ],
	] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'elementor_library' !== $post->post_type ) throw new \RuntimeException( 'Popup not found.' );
		$changed = [];
		if ( array_key_exists( 'triggers', $input ) && is_array( $input['triggers'] ) ) {
			update_post_meta( $id, '_elementor_popup_triggers', $input['triggers'] );
			$changed[] = 'triggers';
		}
		if ( array_key_exists( 'timing', $input ) && is_array( $input['timing'] ) ) {
			update_post_meta( $id, '_elementor_popup_timing', $input['timing'] );
			$changed[] = 'timing';
		}
		if ( array_key_exists( 'conditions', $input ) && is_array( $input['conditions'] ) ) {
			$formatted = [];
			foreach ( $input['conditions'] as $c ) {
				if ( is_array( $c ) ) $formatted[] = implode( '/', array_map( 'strval', $c ) );
				elseif ( is_string( $c ) ) $formatted[] = $c;
			}
			update_post_meta( $id, '_elementor_conditions', $formatted );
			if ( class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ) {
				delete_option( 'elementor_pro_theme_builder_conditions' );
			}
			$changed[] = 'conditions';
		}
		return [ 'updated' => true, 'post_id' => $id, 'changed' => $changed ];
	}
}
