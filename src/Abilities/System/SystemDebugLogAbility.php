<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\System;

use Tropk\Mcp\Abilities\AbstractAbility;

final class SystemDebugLogAbility extends AbstractAbility {
	public function slug(): string { return 'system-debug-log'; }
	protected function meta(): array { return [
		'label' => __( 'Read debug.log', 'mcp-for-wordpress' ),
		'description' => __( 'Reads the tail of wp-content/debug.log when WP_DEBUG_LOG is enabled.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [ 'lines' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 5000, 'default' => 200 ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'enabled' => [ 'type' => 'boolean' ], 'path' => [ 'type' => [ 'string', 'null' ] ], 'lines' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		$enabled = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
		$path    = WP_CONTENT_DIR . '/debug.log';
		if ( ! $enabled || ! is_readable( $path ) ) {
			return [ 'enabled' => false, 'path' => null, 'lines' => [] ];
		}
		$want = (int) ( $input['lines'] ?? 200 );
		$all  = (array) @file( $path, FILE_IGNORE_NEW_LINES );
		$tail = array_slice( $all, -$want );
		return [ 'enabled' => true, 'path' => $path, 'lines' => array_map( 'strval', $tail ) ];
	}
}
