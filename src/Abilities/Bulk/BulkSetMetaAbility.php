<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Bulk;
use Tropk\Mcp\Abilities\AbstractAbility;
final class BulkSetMetaAbility extends AbstractAbility {
	public function slug(): string { return 'bulk-set-meta'; }
	protected function meta(): array { return [ 'label' => __( 'Bulk-set a meta key on many posts', 'mcp-for-wordpress' ), 'description' => __( 'Sets the same meta_key/meta_value on a list of post IDs. Refuses _edit_* and other reserved keys.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'ids', 'meta_key', 'meta_value' ], 'properties' => [ 'ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'maxItems' => 200 ], 'meta_key' => [ 'type' => 'string' ], 'meta_value' => [] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'integer' ], 'failed' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$key = (string) $input['meta_key'];
		if ( str_starts_with( $key, '_edit_' ) ) throw new \RuntimeException( 'Reserved meta key.' );
		$u = 0; $f = 0;
		foreach ( (array) $input['ids'] as $id ) {
			$id = (int) $id;
			if ( ! current_user_can( 'edit_post', $id ) ) { $f++; continue; }
			update_post_meta( $id, $key, $input['meta_value'] ); $u++;
		}
		return [ 'updated' => $u, 'failed' => $f ];
	}
}
