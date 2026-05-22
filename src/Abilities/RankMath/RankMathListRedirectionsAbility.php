<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\RankMath;
use Tropk\Mcp\Abilities\AbstractAbility;
final class RankMathListRedirectionsAbility extends AbstractAbility {
	public function slug(): string { return 'rankmath-list-redirections'; }
	protected function meta(): array { return [ 'label' => __( 'List Rank Math redirections', 'mcp-for-wordpress' ), 'description' => __( 'Returns redirections with source pattern(s), destination, type, status.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => [ 'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 100 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'redirections' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_redirections';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return [ 'redirections' => [] ];
		}
		$limit = max( 1, min( 500, (int) ( $input['limit'] ?? 100 ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, sources, url_to, header_code, status, hits FROM {$table} LIMIT %d", $limit ), ARRAY_A );
		$out = [];
		foreach ( (array) $rows as $r ) {
			$out[] = [
				'id'      => (int) $r['id'],
				'sources' => maybe_unserialize( (string) $r['sources'] ),
				'url_to'  => (string) $r['url_to'],
				'code'    => (int) $r['header_code'],
				'status'  => (string) $r['status'],
				'hits'    => (int) ( $r['hits'] ?? 0 ),
			];
		}
		return [ 'redirections' => $out ];
	}
}
