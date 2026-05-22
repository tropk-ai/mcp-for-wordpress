<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorCreateCustomCodeAbility extends AbstractAbility {
	private const LOCATIONS = [
		'head'       => 'elementor_head',
		'body_start' => 'elementor_body_start',
		'body_end'   => 'elementor_body_end',
	];

	public function slug(): string { return 'elementor-create-custom-code'; }
	protected function meta(): array { return [
		'label'       => __( 'Create Elementor Custom Code snippet', 'mcp-for-wordpress' ),
		'description' => __( 'Creates a site-wide Elementor Pro Custom Code snippet injecting code into head/body_start/body_end.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'title', 'code' ],
		'properties'           => [
			'title'    => [ 'type' => 'string', 'minLength' => 1 ],
			'code'     => [ 'type' => 'string' ],
			'location' => [ 'type' => 'string', 'enum' => [ 'head', 'body_start', 'body_end' ], 'default' => 'head' ],
			'priority' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 1 ],
			'status'   => [ 'type' => 'string', 'enum' => [ 'publish', 'draft' ], 'default' => 'publish' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'id' => [ 'type' => 'integer' ], 'location' => [ 'type' => 'string' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		if ( ! post_type_exists( 'elementor_snippet' ) ) {
			throw new \RuntimeException( 'Elementor Pro Custom Code is not available.' );
		}
		$loc      = (string) ( $input['location'] ?? 'head' );
		$priority = max( 1, min( 10, (int) ( $input['priority'] ?? 1 ) ) );
		$id = wp_insert_post( [
			'post_title'  => sanitize_text_field( (string) $input['title'] ),
			'post_type'   => 'elementor_snippet',
			'post_status' => (string) ( $input['status'] ?? 'publish' ),
		], true );
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message() );
		}
		$location = self::LOCATIONS[ $loc ] ?? 'elementor_head';
		update_post_meta( (int) $id, '_elementor_location', $location );
		update_post_meta( (int) $id, '_elementor_priority', $priority );
		update_post_meta( (int) $id, '_elementor_code', (string) $input['code'] );
		update_post_meta( (int) $id, '_elementor_template_type', 'code_snippet' );
		update_post_meta( (int) $id, '_elementor_edit_mode', 'builder' );
		return [ 'id' => (int) $id, 'location' => $loc ];
	}
}
