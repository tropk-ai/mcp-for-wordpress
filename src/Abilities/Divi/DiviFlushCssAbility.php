<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Divi;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Divi\DiviPage;

final class DiviFlushCssAbility extends AbstractAbility {
	public function slug(): string { return 'divi-flush-css'; }
	protected function meta(): array { return [
		'label'       => __( 'Flush Divi CSS cache', 'mcp-for-wordpress' ),
		'description' => __( 'Clears Divi 5\'s static CSS and asset cache. When post_id is supplied, clears the per-post cache only; otherwise clears the global Divi cache site-wide.', 'mcp-for-wordpress' ),
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties' => [
			'post_id' => [ 'type' => 'integer', 'minimum' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'flushed' => [ 'type' => 'boolean' ],
		'scope'   => [ 'type' => 'string' ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		return isset( $input['post_id'] )
			? current_user_can( 'edit_post', (int) $input['post_id'] )
			: current_user_can( 'manage_options' );
	}
	public function execute( array $input = [] ): array {
		if ( isset( $input['post_id'] ) ) {
			$post_id = (int) $input['post_id'];
			if ( ! DiviPage::is_divi_post( $post_id ) ) {
				throw new \RuntimeException( sprintf( 'Post %d is not a Divi builder page.', $post_id ) );
			}
			DiviPage::load( $post_id )->flush_css();
			return [ 'flushed' => true, 'scope' => 'post' ];
		}

		// Global flush.
		delete_option( 'et_dynamic_assets_version' );
		delete_option( 'et_builder_dynamic_assets_cache' );

		if ( class_exists( '\ET_Core_PageResource' ) ) {
			\ET_Core_PageResource::remove_static_resources( 'all', 'all' );
		}

		return [ 'flushed' => true, 'scope' => 'global' ];
	}
}
