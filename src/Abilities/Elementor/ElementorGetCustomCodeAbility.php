<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorGetCustomCodeAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-custom-code'; }
	protected function meta(): array { return [
		'label'       => __( 'Get Elementor Custom Code snippet', 'mcp-for-wordpress' ),
		'description' => __( 'Returns a single Elementor Pro Custom Code snippet (title, status, location, priority, code).', 'mcp-for-wordpress' ),
		'readonly'    => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'id' ],
		'properties'           => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'id' => [ 'type' => 'integer' ], 'title' => [ 'type' => 'string' ], 'status' => [ 'type' => 'string' ],
		'location' => [ 'type' => 'string' ], 'priority' => [ 'type' => 'integer' ], 'code' => [ 'type' => 'string' ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		$id   = (int) ( $input['id'] ?? 0 );
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof \WP_Post || 'elementor_snippet' !== $post->post_type ) {
			throw new \RuntimeException( 'Snippet not found.' );
		}
		return [
			'id'       => (int) $post->ID,
			'title'    => (string) $post->post_title,
			'status'   => (string) $post->post_status,
			'location' => (string) get_post_meta( $post->ID, '_elementor_location', true ),
			'priority' => (int) get_post_meta( $post->ID, '_elementor_priority', true ),
			'code'     => (string) ( get_post_meta( $post->ID, '_elementor_code', true ) ?: $post->post_content ),
		];
	}
}
