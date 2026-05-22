<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Media;

use Tropk\Mcp\Abilities\AbstractAbility;

final class MediaUploadFromUrlAbility extends AbstractAbility {
	public function slug(): string { return 'media-upload-from-url'; }
	protected function meta(): array { return [
		'label' => __( 'Upload media from URL', 'mcp-for-wordpress' ),
		'description' => __( 'Sideloads an image / file from a remote URL into the media library, optionally attaching to a post.', 'mcp-for-wordpress' ),
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'url' ],
		'properties'           => [
			'url'         => [ 'type' => 'string', 'format' => 'uri' ],
			'parent_post' => [ 'type' => 'integer', 'minimum' => 0 ],
			'title'       => [ 'type' => 'string' ],
			'alt'         => [ 'type' => 'string' ],
			'description' => [ 'type' => 'string' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'attachment_id' => [ 'type' => [ 'integer', 'null' ] ], 'url' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'upload_files' ); }
	public function execute( array $input = [] ): array {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( (string) $input['url'] );
		if ( is_wp_error( $tmp ) ) {
			throw new \RuntimeException( $tmp->get_error_message() );
		}

		$file = [
			'name'     => wp_basename( parse_url( (string) $input['url'], PHP_URL_PATH ) ?: 'upload' ),
			'tmp_name' => $tmp,
		];

		$id = media_handle_sideload( $file, (int) ( $input['parent_post'] ?? 0 ), (string) ( $input['title'] ?? '' ) );
		if ( file_exists( $tmp ) ) {
			@unlink( $tmp );
		}
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message() );
		}
		if ( ! empty( $input['alt'] ) ) {
			update_post_meta( (int) $id, '_wp_attachment_image_alt', (string) $input['alt'] );
		}
		if ( ! empty( $input['description'] ) ) {
			wp_update_post( [ 'ID' => (int) $id, 'post_content' => (string) $input['description'] ] );
		}
		return [ 'attachment_id' => (int) $id, 'url' => (string) wp_get_attachment_url( (int) $id ) ];
	}
}
