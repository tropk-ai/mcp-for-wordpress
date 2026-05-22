<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Content;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ContentGetNextPostAbility extends AbstractAbility {
	public function slug(): string { return 'content-get-next-post'; }
	protected function meta(): array { return [ 'label' => __( 'Get the next post', 'mcp-for-wordpress' ), 'description' => __( 'Returns the post with the smallest ID greater than the given one. Useful when IDs have gaps from deletions.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'after_id' ], 'properties' => [ 'after_id' => [ 'type' => 'integer', 'minimum' => 0 ], 'post_type' => [ 'type' => 'string', 'default' => 'post' ], 'status' => [ 'type' => 'string', 'default' => 'publish' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'found' => [ 'type' => 'boolean' ], 'post_id' => [ 'type' => [ 'integer', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'read' ); }
	public function execute( array $input = [] ): array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT ID, post_title FROM {$wpdb->posts} WHERE ID > %d AND post_type = %s AND post_status = %s ORDER BY ID ASC LIMIT 1",
			(int) $input['after_id'], (string) ( $input['post_type'] ?? 'post' ), (string) ( $input['status'] ?? 'publish' )
		), ARRAY_A );
		return $row ? [ 'found' => true, 'post_id' => (int) $row['ID'], 'title' => (string) $row['post_title'] ] : [ 'found' => false, 'post_id' => null ];
	}
}
