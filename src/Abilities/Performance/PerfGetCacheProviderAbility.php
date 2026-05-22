<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Performance;
use Tropk\Mcp\Abilities\AbstractAbility;
final class PerfGetCacheProviderAbility extends AbstractAbility {
	public function slug(): string { return 'perf-get-cache-provider'; }
	protected function meta(): array { return [ 'label' => __( 'Detect active cache plugin', 'mcp-for-wordpress' ), 'description' => __( 'Returns the active page-cache provider (WP Rocket / LiteSpeed / W3TC / WP Super Cache / Cache Enabler / Autoptimize), or null.', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array { 
		$providers = [
			"WP Rocket"      => function_exists( "rocket_clean_post" ),
			"LiteSpeed"      => class_exists( "LiteSpeed\\Purge" ),
			"W3 Total Cache" => function_exists( "w3tc_flush_post" ),
			"WP Super Cache" => function_exists( "wp_cache_clear_cache" ),
			"Cache Enabler"  => class_exists( "Cache_Enabler" ),
			"Autoptimize"    => class_exists( "autoptimizeCache" ),
		];
		$active = array_keys( array_filter( $providers ) );
		return [ "result" => [ "providers" => $active ] ]; }
}
