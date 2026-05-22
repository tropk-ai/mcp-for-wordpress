<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\RankMath;
use Tropk\Mcp\Abilities\AbstractAbility;
final class RankMathList404LogsAbility extends AbstractAbility {
	public function slug(): string { return 'rankmath-list-404-logs'; }
	protected function meta(): array { return [ 'label' => __( 'List recent 404 logs (Rank Math)', 'mcp-for-wordpress' ), 'description' => __( 'Returns recent 404-monitor rows ordered by hit count.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => [ 'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 100 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'logs' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_404_logs';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return [ 'logs' => [] ];
		}
		$limit = max( 1, min( 500, (int) ( $input['limit'] ?? 100 ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, uri, accessed, times_accessed, referer, user_agent FROM {$table} ORDER BY times_accessed DESC LIMIT %d", $limit ), ARRAY_A );
		return [ 'logs' => is_array( $rows ) ? $rows : [] ];
	}
}
