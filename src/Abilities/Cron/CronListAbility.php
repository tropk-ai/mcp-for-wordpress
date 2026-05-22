<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Cron;
use Tropk\Mcp\Abilities\AbstractAbility;
final class CronListAbility extends AbstractAbility {
	public function slug(): string { return 'cron-list'; }
	protected function meta(): array { return [ 'label' => __( 'List scheduled cron events', 'mcp-for-wordpress' ), 'description' => __( 'Returns every entry in the cron array sorted by timestamp.', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array { 
		$out = [];
		foreach ( (array) _get_cron_array() as $ts => $hooks ) {
			foreach ( $hooks as $hook => $instances ) {
				foreach ( $instances as $sig => $data ) {
					$out[] = [ "timestamp" => (int) $ts, "hook" => (string) $hook, "schedule" => (string) ( $data["schedule"] ?? "" ), "args" => (array) ( $data["args"] ?? [] ) ];
				}
			}
		}
		return [ "result" => [ "events" => $out, "count" => count( $out ) ] ]; }
}
