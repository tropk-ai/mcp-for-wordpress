<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Performance;
use Tropk\Mcp\Abilities\AbstractAbility;
final class PerfFlushRewriteRulesAbility extends AbstractAbility {
	public function slug(): string { return 'perf-flush-rewrite-rules'; }
	protected function meta(): array { return [ 'label' => __( 'Flush rewrite rules', 'mcp-for-wordpress' ), 'description' => __( 'Regenerates the WordPress rewrite rules. Use after registering CPTs or taxonomies.', 'mcp-for-wordpress' ), 'readonly' => false, 'destructive' => false, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array { flush_rewrite_rules( false ); return [ "result" => [ "flushed" => true ] ]; }
}
