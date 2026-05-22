<?php
declare(strict_types=1);

namespace Tropk\Mcp\Support;

final class Capabilities {

	public const DESTRUCTIVE = 'mcp_invoke_destructive_tools';

	public static function user_can( string $capability, ...$args ): bool {
		return current_user_can( $capability, ...$args );
	}

	public static function require_destructive(): bool {
		return current_user_can( self::DESTRUCTIVE );
	}
}
