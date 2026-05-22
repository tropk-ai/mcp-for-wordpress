<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorUploadSvgIconAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-upload-svg-icon'; }
	protected function meta(): array { return [
		'label'       => __( 'Upload SVG icon to Media Library', 'mcp-for-wordpress' ),
		'description' => __( 'Uploads an SVG icon (from raw markup or external URL) into the Media Library and returns an Elementor-compatible icon_object { value:{id,url}, library:"svg" } ready for selected_icon settings.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [
			'svg_url'     => [ 'type' => 'string', 'format' => 'uri' ],
			'svg_content' => [ 'type' => 'string' ],
			'title'       => [ 'type' => 'string' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'attachment_id' => [ 'type' => 'integer' ], 'url' => [ 'type' => 'string' ],
		'icon_object' => [ 'type' => 'object' ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'upload_files' ) && current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$url     = (string) esc_url_raw( (string) ( $input['svg_url'] ?? '' ) );
		$content = (string) ( $input['svg_content'] ?? '' );
		$title   = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		if ( '' === $url && '' === $content ) {
			throw new \RuntimeException( 'Either svg_url or svg_content is required.' );
		}
		if ( '' !== $url && '' !== $content ) {
			throw new \RuntimeException( 'Provide either svg_url or svg_content, not both.' );
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$allow_svg = static function ( $mimes ) { $mimes['svg'] = 'image/svg+xml'; return $mimes; };
		$fix_mime  = static function ( $data, $file, $filename ) {
			$ext = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
			if ( 'svg' === $ext ) {
				$data['ext']             = 'svg';
				$data['type']            = 'image/svg+xml';
				$data['proper_filename'] = $filename;
			}
			return $data;
		};
		add_filter( 'upload_mimes', $allow_svg );
		add_filter( 'wp_check_filetype_and_ext', $fix_mime, 10, 3 );
		try {
			if ( '' !== $url ) {
				$tmp = download_url( $url, 30 );
				if ( is_wp_error( $tmp ) ) {
					throw new \RuntimeException( $tmp->get_error_message() );
				}
				$raw = (string) @file_get_contents( $tmp );
				if ( stripos( $raw, '<svg' ) === false || preg_match( '/<script/i', $raw ) ) {
					@wp_delete_file( $tmp );
					throw new \RuntimeException( 'Downloaded file is not a safe SVG.' );
				}
				$filename = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
				if ( ! preg_match( '/\.svg$/i', $filename ) ) $filename = ( $title !== '' ? sanitize_title( $title ) : 'icon' ) . '.svg';
			} else {
				if ( stripos( $content, '<svg' ) === false ) {
					throw new \RuntimeException( 'svg_content must contain a <svg> element.' );
				}
				$content = (string) preg_replace( '/<\?(=|php)(.+?)\?>/i', '', $content );
				if ( preg_match( '/<script/i', $content ) ) {
					throw new \RuntimeException( 'SVG contains <script> and was rejected.' );
				}
				$content = (string) preg_replace( '/\s+on\w+\s*=\s*(["\']).*?\1/i', '', $content );
				$content = (string) preg_replace( '/javascript\s*:/i', '', $content );
				$tmp = wp_tempnam( 'svg_icon_' );
				if ( ! $tmp ) {
					throw new \RuntimeException( 'Could not allocate temp file.' );
				}
				file_put_contents( $tmp, $content );
				$filename = ( $title !== '' ? sanitize_title( $title ) : 'custom-icon' ) . '.svg';
			}
			$attach_id = media_handle_sideload( [ 'name' => sanitize_file_name( $filename ), 'tmp_name' => $tmp ], 0 );
			if ( is_wp_error( $attach_id ) ) {
				if ( file_exists( $tmp ) ) wp_delete_file( $tmp );
				throw new \RuntimeException( $attach_id->get_error_message() );
			}
			$attach_id = (int) $attach_id;
			if ( '' !== $title ) wp_update_post( [ 'ID' => $attach_id, 'post_title' => $title ] );
			$local = (string) ( wp_get_attachment_url( $attach_id ) ?: '' );
			return [
				'attachment_id' => $attach_id,
				'url'           => $local,
				'icon_object'   => [ 'value' => [ 'id' => $attach_id, 'url' => $local ], 'library' => 'svg' ],
			];
		} finally {
			remove_filter( 'upload_mimes', $allow_svg );
			remove_filter( 'wp_check_filetype_and_ext', $fix_mime, 10 );
		}
	}
}
