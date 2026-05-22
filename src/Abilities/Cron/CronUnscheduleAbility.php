<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Cron;
use Tropk\Mcp\Abilities\AbstractAbility;
final class CronUnscheduleAbility extends AbstractAbility {
	public function slug(): string { return 'cron-unschedule'; }
	protected function meta(): array { return [ 'label' => __( 'Unschedule a cron event', 'mcp-for-wordpress' ), 'description' => __( 'Removes all occurrences of a cron hook from the schedule.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'hook' ], 'properties' => [ 'hook' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'cleared' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array { wp_clear_scheduled_hook( (string) $input['hook'] ); return [ 'cleared' => true ]; }
}
