<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Media;

use Tropk\Mcp\Abilities\AbstractAbility;

final class MediaGetAbility extends AbstractAbility {
	public function slug(): string { return 'media-get'; }
	protected function meta(): array { return [
		'label' => __( 'Get a media attachment', 'mcp-for-wordpress' ),
		'description' => __( 'Returns a single attachment with URL, sizes, mime, and metadata.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'id' ],
		'properties'           => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'id' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'upload_files' ); }
	public function execute( array $input = [] ): array {
		$p = get_post( (int) $input['id'] );
		if ( ! $p instanceof \WP_Post || 'attachment' !== $p->post_type ) {
			throw new \RuntimeException( 'Attachment not found.' );
		}
		$meta = wp_get_attachment_metadata( $p->ID );
		return [
			'id'      => (int) $p->ID,
			'title'   => (string) $p->post_title,
			'url'     => (string) wp_get_attachment_url( $p->ID ),
			'mime'    => (string) $p->post_mime_type,
			'alt'     => (string) get_post_meta( $p->ID, '_wp_attachment_image_alt', true ),
			'sizes'   => is_array( $meta ) ? ( $meta['sizes'] ?? [] ) : [],
			'width'   => is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : 0,
			'height'  => is_array( $meta ) ? (int) ( $meta['height'] ?? 0 ) : 0,
			'date'    => (string) $p->post_date_gmt,
			'parent'  => (int) $p->post_parent,
		];
	}
}
