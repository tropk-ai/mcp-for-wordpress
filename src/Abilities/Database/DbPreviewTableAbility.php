<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Database;
use Tropk\Mcp\Abilities\AbstractAbility;
final class DbPreviewTableAbility extends AbstractAbility {
	public function slug(): string { return 'db-preview-table'; }
	protected function meta(): array { return [ 'label' => __( 'Preview rows from a table', 'mcp-for-wordpress' ), 'description' => __( 'Returns the first N rows of a table for inspection.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'table' ], 'properties' => [ 'table' => [ 'type' => 'string' ], 'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'rows' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		global $wpdb;
		$table = (string) $input['table'];
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table ) ) throw new \RuntimeException( 'Invalid table name.' );
		$limit = max( 1, min( 100, (int) ( $input['limit'] ?? 10 ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` LIMIT %d", $limit ), ARRAY_A );
		return [ 'rows' => is_array( $rows ) ? $rows : [] ];
	}
}
