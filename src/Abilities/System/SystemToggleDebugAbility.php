<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\System;

use Tropk\Mcp\Abilities\AbstractAbility;

final class SystemToggleDebugAbility extends AbstractAbility {
	public function slug(): string { return 'system-toggle-debug'; }
	protected function meta(): array { return [
		'label' => __( 'Toggle WP_DEBUG state', 'mcp-for-wordpress' ),
		'description' => __( 'Reports the current state of WP_DEBUG / WP_DEBUG_LOG / WP_DEBUG_DISPLAY constants. Cannot change them at runtime — returns guidance instead.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [
		'wp_debug' => [ 'type' => 'boolean' ],
		'wp_debug_log' => [ 'type' => 'boolean' ],
		'wp_debug_display' => [ 'type' => 'boolean' ],
		'log_path' => [ 'type' => [ 'string', 'null' ] ],
		'note' => [ 'type' => 'string' ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		return [
			'wp_debug'         => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_log'     => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'wp_debug_display' => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
			'log_path'         => ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ? WP_CONTENT_DIR . '/debug.log' : null,
			'note'             => __( 'PHP constants cannot be changed at runtime. To turn debug on, edit wp-config.php and add: define(\'WP_DEBUG\', true); define(\'WP_DEBUG_LOG\', true);', 'mcp-for-wordpress' ),
		];
	}
}
