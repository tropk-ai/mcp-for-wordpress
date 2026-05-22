<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\RankMath;
use Tropk\Mcp\Abilities\AbstractAbility;
final class RankMathClear404LogsAbility extends AbstractAbility {
	public function slug(): string { return 'rankmath-clear-404-logs'; }
	protected function meta(): array { return [ 'label' => __( 'Clear Rank Math 404 logs', 'mcp-for-wordpress' ), 'description' => __( 'Empties the Rank Math 404 monitor table.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'cleared' => [ 'type' => 'boolean' ], 'rows' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_404_logs';
		$rows = (int) $wpdb->query( "DELETE FROM {$table}" );
		return [ 'cleared' => true, 'rows' => $rows ];
	}
}
