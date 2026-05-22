<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorSetActiveKitAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-set-active-kit'; }
	protected function meta(): array { return [
		'label'       => __( 'Set active Elementor Kit', 'mcp-for-wordpress' ),
		'description' => __( 'Switches the currently-active Elementor Kit to the supplied kit ID.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'kit_id' ],
		'properties'           => [
			'kit_id' => [ 'type' => 'integer', 'minimum' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'kit_id' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$kit_id = (int) ( $input['kit_id'] ?? 0 );
		if ( $kit_id <= 0 ) {
			throw new \RuntimeException( 'kit_id is required.' );
		}
		$kit = get_post( $kit_id );
		if ( ! $kit instanceof \WP_Post || 'elementor_library' !== $kit->post_type ) {
			throw new \RuntimeException( 'Kit not found.' );
		}
		update_option( 'elementor_active_kit', $kit_id );
		if ( class_exists( '\\Elementor\\Plugin' ) ) {
			$el = \Elementor\Plugin::$instance ?? null;
			if ( $el && isset( $el->files_manager ) && method_exists( $el->files_manager, 'clear_cache' ) ) {
				$el->files_manager->clear_cache();
			}
		}
		return [ 'kit_id' => $kit_id ];
	}
}
