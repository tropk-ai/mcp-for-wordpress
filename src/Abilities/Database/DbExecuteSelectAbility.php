<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Database;
use Tropk\Mcp\Abilities\AbstractAbility;
final class DbExecuteSelectAbility extends AbstractAbility {
	public function slug(): string { return 'db-execute-select'; }
	protected function meta(): array { return [ 'label' => __( 'Run a read-only SELECT', 'mcp-for-wordpress' ), 'description' => __( "Runs a SELECT query against the WordPress database. Any keyword that mutates state (INSERT/UPDATE/DELETE/DROP/ALTER/CREATE/TRUNCATE/REPLACE) is refused at the SQL parser layer.", 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'query' ], 'properties' => [ 'query' => [ 'type' => 'string', 'minLength' => 7 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'rows' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		$q = trim( (string) $input['query'] );
		if ( ! preg_match( '/^select\s+/i', $q ) ) throw new \RuntimeException( 'Only SELECT queries are allowed.' );
		if ( preg_match( '/\b(insert|update|delete|drop|alter|create|truncate|replace|grant|revoke)\b/i', $q ) ) {
			throw new \RuntimeException( 'Mutating keyword detected — refused.' );
		}
		global $wpdb;
		$rows = $wpdb->get_results( $q, ARRAY_A );
		if ( null === $rows ) throw new \RuntimeException( $wpdb->last_error ?: 'Query failed.' );
		return [ 'rows' => $rows, 'count' => count( $rows ) ];
	}
}
