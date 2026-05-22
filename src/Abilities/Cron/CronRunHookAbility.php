<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Cron;
use Tropk\Mcp\Abilities\AbstractAbility;
final class CronRunHookAbility extends AbstractAbility {
	public function slug(): string { return 'cron-run-hook'; }
	protected function meta(): array { return [ 'label' => __( 'Run a cron hook now', 'mcp-for-wordpress' ), 'description' => __( 'Manually triggers the next pending invocation of a scheduled hook.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'hook' ], 'properties' => [ 'hook' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'ran' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$next = wp_next_scheduled( (string) $input['hook'] );
		if ( ! $next ) return [ 'ran' => false ];
		do_action_ref_array( (string) $input['hook'], [] );
		return [ 'ran' => true ];
	}
}
