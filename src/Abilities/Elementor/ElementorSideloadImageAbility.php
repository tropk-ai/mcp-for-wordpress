<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorSideloadImageAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-sideload-image'; }
	protected function meta(): array { return [
		'label'       => __( 'Sideload external image into Media Library', 'mcp-for-wordpress' ),
		'description' => __( 'Downloads an external image URL into the WP Media Library and returns the local attachment ID + URL.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'url' ],
		'properties'           => [
			'url'         => [ 'type' => 'string', 'format' => 'uri' ],
			'title'       => [ 'type' => 'string' ],
			'alt_text'    => [ 'type' => 'string' ],
			'caption'     => [ 'type' => 'string' ],
			'attribution' => [ 'type' => 'string' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'attachment_id' => [ 'type' => 'integer' ], 'url' => [ 'type' => 'string' ], 'title' => [ 'type' => 'string' ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'upload_files' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$url = (string) esc_url_raw( (string) ( $input['url'] ?? '' ) );
		if ( '' === $url ) {
			throw new \RuntimeException( 'url is required.' );
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			throw new \RuntimeException( $tmp->get_error_message() );
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$name = $path ? basename( (string) $path ) : 'image.jpg';
		if ( ! preg_match( '/\.\w+$/', $name ) ) $name .= '.jpg';
		$attach_id = media_handle_sideload( [ 'name' => sanitize_file_name( $name ), 'tmp_name' => $tmp ], 0 );
		if ( is_wp_error( $attach_id ) ) {
			if ( file_exists( $tmp ) ) wp_delete_file( $tmp );
			throw new \RuntimeException( $attach_id->get_error_message() );
		}
		$attach_id = (int) $attach_id;
		$title = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		if ( '' !== $title ) {
			wp_update_post( [ 'ID' => $attach_id, 'post_title' => $title ] );
		} else {
			$title = (string) get_the_title( $attach_id );
		}
		$alt = sanitize_text_field( (string) ( $input['alt_text'] ?? '' ) );
		if ( '' !== $alt ) update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt );
		$excerpt = sanitize_text_field( (string) ( $input['caption'] ?? $input['attribution'] ?? '' ) );
		if ( '' !== $excerpt ) wp_update_post( [ 'ID' => $attach_id, 'post_excerpt' => $excerpt ] );
		return [
			'attachment_id' => $attach_id,
			'url'           => (string) ( wp_get_attachment_url( $attach_id ) ?: '' ),
			'title'         => $title,
		];
	}
}
