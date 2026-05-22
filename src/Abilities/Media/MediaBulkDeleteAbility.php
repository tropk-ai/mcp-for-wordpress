<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Media;
use Tropk\Mcp\Abilities\AbstractAbility;
final class MediaBulkDeleteAbility extends AbstractAbility {
	public function slug(): string { return 'media-bulk-delete'; }
	protected function meta(): array { return [ 'label' => __( 'Bulk-delete media attachments', 'mcp-for-wordpress' ), 'description' => __( 'Permanently deletes many attachments at once.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'ids' ], 'properties' => [ 'ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'maxItems' => 200 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deleted' => [ 'type' => 'integer' ], 'failed' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$d = 0; $f = 0;
		foreach ( (array) $input['ids'] as $id ) {
			$id = (int) $id;
			if ( ! current_user_can( 'delete_post', $id ) ) { $f++; continue; }
			wp_delete_attachment( $id, true ) ? $d++ : $f++;
		}
		return [ 'deleted' => $d, 'failed' => $f ];
	}
}
