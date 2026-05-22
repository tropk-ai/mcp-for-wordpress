<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Media;
use Tropk\Mcp\Abilities\AbstractAbility;
final class MediaFindUnusedAbility extends AbstractAbility {
	public function slug(): string { return 'media-find-unused'; }
	protected function meta(): array { return [ 'label' => __( 'Find unused media', 'mcp-for-wordpress' ), 'description' => __( "Returns attachments whose URL doesn't appear in any post content or _elementor_data. Useful for cleanup.", 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => [ 'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 100 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'unused' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'upload_files' ); }
	public function execute( array $input = [] ): array {
		global $wpdb;
		$limit = max( 1, min( 500, (int) ( $input['limit'] ?? 100 ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_title, guid FROM {$wpdb->posts} WHERE post_type = 'attachment' LIMIT %d", $limit
		), ARRAY_A );
		$out = [];
		foreach ( (array) $rows as $r ) {
			$url = (string) $r['guid'];
			$basename = wp_basename( $url );
			$used = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND (post_content LIKE %s OR post_content LIKE %s)",
				'%' . $wpdb->esc_like( $url ) . '%',
				'%' . $wpdb->esc_like( $basename ) . '%'
			) );
			if ( 0 === $used ) $out[] = [ 'id' => (int) $r['ID'], 'title' => (string) $r['post_title'], 'url' => $url ];
		}
		return [ 'unused' => $out ];
	}
}
