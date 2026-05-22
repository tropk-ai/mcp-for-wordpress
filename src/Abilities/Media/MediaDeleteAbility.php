<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Media;

use Tropk\Mcp\Abilities\AbstractAbility;

final class MediaDeleteAbility extends AbstractAbility {
	public function slug(): string { return 'media-delete'; }
	protected function meta(): array { return [
		'label' => __( 'Delete a media attachment', 'mcp-for-wordpress' ),
		'description' => __( 'Permanently deletes an attachment and its file.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'id' ],
		'properties'           => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deleted' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'delete_post', (int) ( $input['id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$ok = (bool) wp_delete_attachment( (int) $input['id'], true );
		return [ 'deleted' => $ok ];
	}
}
