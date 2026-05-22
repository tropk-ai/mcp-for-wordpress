<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorAddCodeSnippetAbility extends AbstractAbility {
	private const LOCATIONS = [
		'head'       => 'elementor_head',
		'body_start' => 'elementor_body_start',
		'body_end'   => 'elementor_body_end',
	];

	public function slug(): string { return 'elementor-add-code-snippet'; }
	protected function meta(): array { return [
		'label'       => __( 'Add Elementor Pro Custom Code snippet', 'mcp-for-wordpress' ),
		'description' => __( "Convenience wrapper around elementor-create-custom-code that injects analytics / tracking / site-wide overrides into <head>, body_start or body_end.", 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'title', 'code' ],
		'properties'           => [
			'title'         => [ 'type' => 'string', 'minLength' => 1 ],
			'code'          => [ 'type' => 'string' ],
			'location'      => [ 'type' => 'string', 'enum' => [ 'head', 'body_start', 'body_end' ], 'default' => 'head' ],
			'priority'      => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 1 ],
			'status'        => [ 'type' => 'string', 'enum' => [ 'publish', 'draft' ], 'default' => 'publish' ],
			'ensure_jquery' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'snippet_id' => [ 'type' => 'integer' ], 'location' => [ 'type' => 'string' ],
		'priority'   => [ 'type' => 'integer' ], 'status' => [ 'type' => 'string' ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		if ( ! post_type_exists( 'elementor_snippet' ) ) {
			throw new \RuntimeException( 'Elementor Pro Custom Code is not available.' );
		}
		$loc      = (string) ( $input['location'] ?? 'head' );
		$priority = max( 1, min( 10, (int) ( $input['priority'] ?? 1 ) ) );
		$status   = (string) ( $input['status'] ?? 'publish' );
		$id = wp_insert_post( [
			'post_title'  => sanitize_text_field( (string) $input['title'] ),
			'post_type'   => 'elementor_snippet',
			'post_status' => $status,
		], true );
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message() );
		}
		update_post_meta( (int) $id, '_elementor_location', self::LOCATIONS[ $loc ] ?? 'elementor_head' );
		update_post_meta( (int) $id, '_elementor_priority', $priority );
		update_post_meta( (int) $id, '_elementor_code', (string) $input['code'] );
		update_post_meta( (int) $id, '_elementor_template_type', 'code_snippet' );
		update_post_meta( (int) $id, '_elementor_edit_mode', 'builder' );
		if ( ! empty( $input['ensure_jquery'] ) ) {
			update_post_meta( (int) $id, '_elementor_extra_options', [ 'ensure_jquery' ] );
		}
		return [ 'snippet_id' => (int) $id, 'location' => $loc, 'priority' => $priority, 'status' => $status ];
	}
}
