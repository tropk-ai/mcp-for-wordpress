<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorGetPageHtmlAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-page-html'; }
	protected function meta(): array { return [ 'label' => __( 'Render an Elementor page to HTML', 'mcp-for-wordpress' ), 'description' => __( "Fetches the public permalink and returns the rendered <body> HTML. Useful for snapshotting visual state before mutations.", 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'url' => [ 'type' => 'string' ], 'html_length' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$url = (string) get_permalink( (int) $input['post_id'] );
		$resp = wp_remote_get( $url, [ 'timeout' => 10 ] );
		if ( is_wp_error( $resp ) ) throw new \RuntimeException( $resp->get_error_message() );
		$body = (string) wp_remote_retrieve_body( $resp );
		return [ 'url' => $url, 'html_length' => strlen( $body ), 'status' => (int) wp_remote_retrieve_response_code( $resp ) ];
	}
}
