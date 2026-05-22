<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Content;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ContentBulkUpdatePostsAbility extends AbstractAbility {
	public function slug(): string { return 'content-bulk-update-posts'; }
	protected function meta(): array { return [ 'label' => __( 'Bulk-update posts', 'mcp-for-wordpress' ), 'description' => __( 'Applies the same patch to many posts (e.g. set status=publish on 50 drafts).', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required' => [ 'ids', 'patch' ],
		'properties' => [
			'ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'maxItems' => 100 ],
			'patch' => [ 'type' => 'object' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'integer' ], 'failed' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$map = [ 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'status' => 'post_status' ];
		$args = [];
		foreach ( (array) ( $input['patch'] ?? [] ) as $k => $v ) {
			if ( isset( $map[ $k ] ) ) $args[ $map[ $k ] ] = $v;
		}
		$u = 0; $f = 0;
		foreach ( (array) $input['ids'] as $id ) {
			$id = (int) $id;
			if ( ! current_user_can( 'edit_post', $id ) ) { $f++; continue; }
			$args['ID'] = $id;
			$res = wp_update_post( $args, true );
			is_wp_error( $res ) ? $f++ : $u++;
		}
		return [ 'updated' => $u, 'failed' => $f ];
	}
}
