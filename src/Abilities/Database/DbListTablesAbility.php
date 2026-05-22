<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Database;
use Tropk\Mcp\Abilities\AbstractAbility;
final class DbListTablesAbility extends AbstractAbility {
	public function slug(): string { return 'db-list-tables'; }
	protected function meta(): array { return [ 'label' => __( 'List database tables', 'mcp-for-wordpress' ), 'description' => __( 'Returns every table in the WordPress database with row count estimate.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'tables' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		global $wpdb;
		$rows = $wpdb->get_results( "SHOW TABLE STATUS", ARRAY_A );
		$out = [];
		foreach ( (array) $rows as $r ) {
			$out[] = [ 'name' => (string) $r['Name'], 'rows' => (int) $r['Rows'], 'data_size' => (int) $r['Data_length'], 'engine' => (string) $r['Engine'] ];
		}
		return [ 'tables' => $out ];
	}
}
