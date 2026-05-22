<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Media;

use Tropk\Mcp\Abilities\AbstractAbility;

final class MediaUploadBase64Ability extends AbstractAbility {
	public function slug(): string { return 'media-upload-base64'; }
	protected function meta(): array { return [
		'label' => __( 'Upload media (base64 payload)', 'mcp-for-wordpress' ),
		'description' => __( 'Decodes a base64 payload and inserts it into the media library. Use this when the AI client supplies an image inline.', 'mcp-for-wordpress' ),
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'filename', 'data' ],
		'properties'           => [
			'filename'    => [ 'type' => 'string', 'minLength' => 1 ],
			'data'        => [ 'type' => 'string', 'description' => __( 'Base64-encoded payload (with or without data: prefix).', 'mcp-for-wordpress' ) ],
			'parent_post' => [ 'type' => 'integer', 'minimum' => 0 ],
			'alt'         => [ 'type' => 'string' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'attachment_id' => [ 'type' => [ 'integer', 'null' ] ], 'url' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'upload_files' ); }
	public function execute( array $input = [] ): array {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$data = (string) $input['data'];
		if ( str_starts_with( $data, 'data:' ) ) {
			$pos  = strpos( $data, ',' );
			$data = false !== $pos ? substr( $data, $pos + 1 ) : '';
		}
		$bytes = base64_decode( $data, true );
		if ( false === $bytes || '' === $bytes ) {
			throw new \RuntimeException( 'Invalid base64 payload.' );
		}

		$uploads = wp_upload_dir();
		$tmp     = wp_tempnam( (string) $input['filename'] );
		if ( ! $tmp || false === file_put_contents( $tmp, $bytes ) ) {
			throw new \RuntimeException( 'Cannot write temp file.' );
		}

		$file = [ 'name' => sanitize_file_name( (string) $input['filename'] ), 'tmp_name' => $tmp ];
		$id   = media_handle_sideload( $file, (int) ( $input['parent_post'] ?? 0 ) );
		if ( file_exists( $tmp ) ) {
			@unlink( $tmp );
		}
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message() );
		}
		if ( ! empty( $input['alt'] ) ) {
			update_post_meta( (int) $id, '_wp_attachment_image_alt', (string) $input['alt'] );
		}
		return [ 'attachment_id' => (int) $id, 'url' => (string) wp_get_attachment_url( (int) $id ) ];
	}
}
