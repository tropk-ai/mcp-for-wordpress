<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorFlushCssAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-flush-css'; }
	protected function meta(): array { return [
		'label' => __( 'Flush Elementor CSS cache', 'mcp-for-wordpress' ),
		'description' => __( 'Clears Elementor pre-generated CSS for a specific post (when post_id is supplied) or the entire site. Calls files_manager->clear_cache() when available.', 'mcp-for-wordpress' ),
		'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'flushed' => [ 'type' => 'boolean' ], 'scope' => [ 'type' => 'string' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return isset( $input['post_id'] ) ? current_user_can( 'edit_post', (int) $input['post_id'] ) : current_user_can( 'manage_options' );
	}
	public function execute( array $input = [] ): array {
		if ( isset( $input['post_id'] ) ) {
			ElementorPage::load( (int) $input['post_id'] )->flush_css();
			return [ 'flushed' => true, 'scope' => 'post' ];
		}
		delete_option( '_elementor_global_css' );
		if ( class_exists( '\\Elementor\\Plugin' ) ) {
			$el = \Elementor\Plugin::$instance ?? null;
			if ( $el && isset( $el->files_manager ) && method_exists( $el->files_manager, 'clear_cache' ) ) {
				$el->files_manager->clear_cache();
			}
		}
		return [ 'flushed' => true, 'scope' => 'global' ];
	}
}
