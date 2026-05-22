<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Cron;
use Tropk\Mcp\Abilities\AbstractAbility;
final class CronScheduleAbility extends AbstractAbility {
	public function slug(): string { return 'cron-schedule'; }
	protected function meta(): array { return [ 'label' => __( 'Schedule a cron event', 'mcp-for-wordpress' ), 'description' => __( 'Schedules a single or recurring cron event.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'hook', 'timestamp' ], 'properties' => [ 'hook' => [ 'type' => 'string' ], 'timestamp' => [ 'type' => 'integer' ], 'recurrence' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'scheduled' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$hook = (string) $input['hook']; $ts = (int) $input['timestamp']; $rec = (string) ( $input['recurrence'] ?? '' );
		$ok = $rec ? wp_schedule_event( $ts, $rec, $hook ) : wp_schedule_single_event( $ts, $hook );
		return [ 'scheduled' => false !== $ok ];
	}
}
