<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\System;

use Tropk\Mcp\Abilities\AbstractAbility;

final class SystemGetTransientAbility extends AbstractAbility {
	public function slug(): string { return 'system-get-transient'; }
	protected function meta(): array { return [
		'label' => __( 'Get a transient', 'mcp-for-wordpress' ),
		'description' => __( 'Reads a transient by key. Useful for inspecting cached values without invalidating them.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'key' ],
		'properties'           => [ 'key' => [ 'type' => 'string', 'minLength' => 1 ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'key' => [ 'type' => 'string' ], 'present' => [ 'type' => 'boolean' ], 'value' => [] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		$val = get_transient( (string) $input['key'] );
		return [ 'key' => (string) $input['key'], 'present' => false !== $val, 'value' => $val ];
	}
}
