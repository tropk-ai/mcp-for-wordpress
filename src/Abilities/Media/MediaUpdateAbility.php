<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Media;

use Tropk\Mcp\Abilities\AbstractAbility;

final class MediaUpdateAbility extends AbstractAbility {
	public function slug(): string { return 'media-update'; }
	protected function meta(): array { return [
		'label' => __( 'Update media metadata', 'mcp-for-wordpress' ),
		'description' => __( 'Updates the title, alt text, caption, and description of an attachment.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'id' ],
		'properties'           => [
			'id'          => [ 'type' => 'integer', 'minimum' => 1 ],
			'title'       => [ 'type' => 'string' ],
			'alt'         => [ 'type' => 'string' ],
			'caption'     => [ 'type' => 'string' ],
			'description' => [ 'type' => 'string' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$id  = (int) $input['id'];
		$set = [ 'ID' => $id ];
		if ( isset( $input['title'] ) ) $set['post_title']   = (string) $input['title'];
		if ( isset( $input['caption'] ) ) $set['post_excerpt'] = (string) $input['caption'];
		if ( isset( $input['description'] ) ) $set['post_content'] = (string) $input['description'];
		if ( count( $set ) > 1 ) {
			wp_update_post( $set );
		}
		if ( isset( $input['alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', (string) $input['alt'] );
		}
		return [ 'updated' => true ];
	}
}
