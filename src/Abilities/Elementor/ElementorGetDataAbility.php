<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorGetDataAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-data'; }
	protected function meta(): array { return [
		'label' => __( 'Get Elementor data', 'mcp-for-wordpress' ),
		'description' => __( 'Returns the raw Elementor JSON tree (_elementor_data) for a post, along with edit mode and page settings.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [
			'post_id' => [ 'type' => 'integer', 'minimum' => 1 ],
			'format'  => [ 'type' => 'string', 'enum' => [ 'array', 'json' ], 'default' => 'array' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'post_id' => [ 'type' => 'integer' ], 'title' => [ 'type' => 'string' ],
		'edit_mode' => [ 'type' => 'string' ], 'data' => [ 'type' => [ 'array', 'string' ] ],
		'page_settings' => [ 'type' => [ 'object', 'array' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$id   = (int) $input['post_id'];
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post ) {
			throw new \RuntimeException( sprintf( 'Post %d not found.', $id ) );
		}
		$raw           = get_post_meta( $id, '_elementor_data', true );
		$edit_mode     = (string) get_post_meta( $id, '_elementor_edit_mode', true );
		$page_settings = get_post_meta( $id, '_elementor_page_settings', true );
		$format        = (string) ( $input['format'] ?? 'array' );
		if ( 'json' === $format ) {
			$data = is_string( $raw ) ? $raw : (string) wp_json_encode( ElementorPage::load( $id )->data() );
		} else {
			$data = ElementorPage::load( $id )->data();
		}
		return [
			'post_id'       => $id,
			'title'         => (string) $post->post_title,
			'edit_mode'     => '' !== $edit_mode ? $edit_mode : 'not set',
			'data'          => $data,
			'page_settings' => is_array( $page_settings ) ? $page_settings : [],
		];
	}
}
