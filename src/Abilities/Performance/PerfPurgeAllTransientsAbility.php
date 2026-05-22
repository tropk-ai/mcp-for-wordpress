<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Performance;
use Tropk\Mcp\Abilities\AbstractAbility;
final class PerfPurgeAllTransientsAbility extends AbstractAbility {
	public function slug(): string { return 'perf-purge-all-transients'; }
	protected function meta(): array { return [ 'label' => __( 'Purge expired transients', 'mcp-for-wordpress' ), 'description' => __( 'Deletes every expired transient (and timeout key) from wp_options.', 'mcp-for-wordpress' ), 'readonly' => false, 'destructive' => false, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array { 
		global $wpdb;
		$count = (int) $wpdb->query( "DELETE a, b FROM {$wpdb->options} a JOIN {$wpdb->options} b ON b.option_name = CONCAT(\"_transient_timeout_\", SUBSTRING(a.option_name, 12)) WHERE a.option_name LIKE \"\\_transient\\_%\" AND a.option_name NOT LIKE \"\\_transient\\_timeout%\" AND b.option_value < UNIX_TIMESTAMP()" );
		return [ "result" => [ "purged" => $count ] ]; }
}
