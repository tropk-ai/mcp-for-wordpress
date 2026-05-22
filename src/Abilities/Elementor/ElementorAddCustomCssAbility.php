<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorAddCustomCssAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-add-custom-css'; }
	protected function meta(): array { return [
		'label'       => __( 'Add Elementor custom CSS', 'mcp-for-wordpress' ),
		'description' => __( "Appends (or replaces) page-level custom CSS in _elementor_page_settings. Requires Elementor Pro to render at the page level.", 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'css' ],
		'properties'           => [
			'post_id' => [ 'type' => 'integer', 'minimum' => 1 ],
			'css'     => [ 'type' => 'string' ],
			'replace' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'css' => [ 'type' => 'string' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['post_id'] ?? 0 );
		return $id > 0 && current_user_can( 'edit_post', $id ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$post_id = (int) ( $input['post_id'] ?? 0 );
		$css     = (string) ( $input['css'] ?? '' );
		$replace = ! empty( $input['replace'] );
		if ( ! get_post( $post_id ) ) {
			throw new \RuntimeException( 'Post not found.' );
		}
		$css = (string) preg_replace( '/<\?(=|php)(.+?)\?>/is', '', $css );
		$css = (string) preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $css );
		$settings = get_post_meta( $post_id, '_elementor_page_settings', true );
		$settings = is_array( $settings ) ? $settings : [];
		$existing = (string) ( $settings['custom_css'] ?? '' );
		$new      = $replace ? $css : trim( $existing . "\n" . $css );
		$settings['custom_css'] = $new;
		update_post_meta( $post_id, '_elementor_page_settings', $settings );
		delete_post_meta( $post_id, '_elementor_css' );
		return [ 'post_id' => $post_id, 'css' => $new ];
	}
}
