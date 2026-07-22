<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Meta;
use Tropk\Mcp\Abilities\AbstractAbility;
final class MetaGetPostMetaAbility extends AbstractAbility {
	public function slug(): string { return 'meta-get-post-meta'; }
	protected function meta(): array { return [ 'label' => __( 'Get post meta', 'mcp-for-wordpress' ), 'description' => __( 'Returns a postmeta value or every key when meta_key is omitted.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'meta_key' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'meta' => [ 'description' => 'Post meta as key/value map.' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'read_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		if ( isset( $input['meta_key'] ) && '' !== $input['meta_key'] ) {
			return [ 'meta' => get_post_meta( (int) $input['post_id'], (string) $input['meta_key'], true ) ];
		}
		return [ 'meta' => get_post_meta( (int) $input['post_id'] ) ];
	}
}
