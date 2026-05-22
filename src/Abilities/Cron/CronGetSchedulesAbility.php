<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Cron;
use Tropk\Mcp\Abilities\AbstractAbility;
final class CronGetSchedulesAbility extends AbstractAbility {
	public function slug(): string { return 'cron-get-schedules'; }
	protected function meta(): array { return [ 'label' => __( 'Get cron recurrence options', 'mcp-for-wordpress' ), 'description' => __( 'Returns every registered recurrence (hourly / twicedaily / daily / etc) with its interval in seconds.', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array { 
		$out = [];
		foreach ( (array) wp_get_schedules() as $name => $s ) {
			$out[] = [ "name" => (string) $name, "interval" => (int) ( $s["interval"] ?? 0 ), "display" => (string) ( $s["display"] ?? "" ) ];
		}
		return [ "result" => [ "schedules" => $out ] ]; }
}
