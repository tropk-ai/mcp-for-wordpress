<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Security;
use Tropk\Mcp\Abilities\AbstractAbility;
final class SecurityCountFailedLoginsAbility extends AbstractAbility {
	public function slug(): string { return 'security-count-failed-logins'; }
	protected function meta(): array { return [ 'label' => __( 'Estimate recent failed logins', 'mcp-for-wordpress' ), 'description' => __( 'Uses our own MCP audit log to count failed-auth events in the last hour.', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array { 
		global $wpdb;
		$tbl = $wpdb->prefix . "mcp_audit_log";
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tbl ) ) !== $tbl ) {
			return [ "result" => [ "available" => false ] ];
		}
		$since = gmdate( "Y-m-d H:i:s", time() - HOUR_IN_SECONDS );
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE status = %s AND created_at > %s", "error", $since ) );
		return [ "result" => [ "available" => true, "count" => $n ] ]; }
}
