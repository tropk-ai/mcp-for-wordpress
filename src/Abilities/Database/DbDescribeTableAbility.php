<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Database;
use Tropk\Mcp\Abilities\AbstractAbility;
final class DbDescribeTableAbility extends AbstractAbility {
	public function slug(): string { return 'db-describe-table'; }
	protected function meta(): array { return [ 'label' => __( 'Describe a database table', 'mcp-for-wordpress' ), 'description' => __( 'Returns the column definitions for the given table.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'table' ], 'properties' => [ 'table' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'columns' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		global $wpdb;
		$table = (string) $input['table'];
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table ) ) throw new \RuntimeException( 'Invalid table name.' );
		$rows = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A );
		return [ 'columns' => is_array( $rows ) ? $rows : [] ];
	}
}
