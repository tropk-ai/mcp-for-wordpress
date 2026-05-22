<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorUpdateCustomCodeAbility extends AbstractAbility {
	private const LOCATIONS = [
		'head'       => 'elementor_head',
		'body_start' => 'elementor_body_start',
		'body_end'   => 'elementor_body_end',
	];

	public function slug(): string { return 'elementor-update-custom-code'; }
	protected function meta(): array { return [
		'label'       => __( 'Update Elementor Custom Code snippet', 'mcp-for-wordpress' ),
		'description' => __( 'Updates an existing Elementor Pro Custom Code snippet (title/code/status/location/priority).', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'id' ],
		'properties'           => [
			'id'       => [ 'type' => 'integer', 'minimum' => 1 ],
			'title'    => [ 'type' => 'string' ],
			'code'     => [ 'type' => 'string' ],
			'status'   => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'private' ] ],
			'location' => [ 'type' => 'string', 'enum' => [ 'head', 'body_start', 'body_end' ] ],
			'priority' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 10 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'id' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['id'] ?? 0 );
		return $id > 0 && current_user_can( 'edit_post', $id ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$id   = (int) ( $input['id'] ?? 0 );
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'elementor_snippet' !== $post->post_type ) {
			throw new \RuntimeException( 'Snippet not found.' );
		}
		$update = [ 'ID' => $id ];
		if ( array_key_exists( 'title', $input ) ) $update['post_title'] = sanitize_text_field( (string) $input['title'] );
		if ( array_key_exists( 'code', $input ) ) $update['post_content'] = (string) $input['code'];
		if ( ! empty( $input['status'] ) ) $update['post_status'] = (string) $input['status'];
		if ( count( $update ) > 1 ) {
			$res = wp_update_post( $update, true );
			if ( is_wp_error( $res ) ) {
				throw new \RuntimeException( $res->get_error_message() );
			}
		}
		if ( array_key_exists( 'code', $input ) ) {
			update_post_meta( $id, '_elementor_code', (string) $input['code'] );
		}
		if ( ! empty( $input['location'] ) ) {
			update_post_meta( $id, '_elementor_location', self::LOCATIONS[ (string) $input['location'] ] ?? 'elementor_head' );
		}
		if ( array_key_exists( 'priority', $input ) ) {
			update_post_meta( $id, '_elementor_priority', max( 1, min( 10, (int) $input['priority'] ) ) );
		}
		return [ 'id' => $id ];
	}
}
