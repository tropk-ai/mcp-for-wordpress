<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Performance;
use Tropk\Mcp\Abilities\AbstractAbility;
final class PerfFlushObjectCacheAbility extends AbstractAbility {
	public function slug(): string { return 'perf-flush-object-cache'; }
	protected function meta(): array { return [ 'label' => __( 'Flush object cache', 'mcp-for-wordpress' ), 'description' => __( 'Calls wp_cache_flush() to purge the in-process or external object cache.', 'mcp-for-wordpress' ), 'readonly' => false, 'destructive' => false, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array { wp_cache_flush(); return [ "result" => [ "flushed" => true ] ]; }
}
