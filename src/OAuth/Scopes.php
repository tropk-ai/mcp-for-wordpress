<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth;

/**
 * Catalog of MCP scopes the plugin recognizes. Tools self-declare a
 * required scope through their ability annotations; the scope catalog
 * here is the user-visible vocabulary advertised to clients via
 * scopes_supported in the AS metadata.
 */
final class Scopes {

	public const READ         = 'mcp:read';
	public const WRITE        = 'mcp:write';
	public const DESTRUCTIVE  = 'mcp:destructive';
	public const ADMIN        = 'mcp:admin';
	public const OPENID       = 'openid';
	public const OFFLINE      = 'offline_access';

	public const ALL = [
		self::READ,
		self::WRITE,
		self::DESTRUCTIVE,
		self::ADMIN,
		self::OPENID,
		self::OFFLINE,
	];

	public const DEFAULT_REQUESTED = [ self::READ ];

	/**
	 * @param string $scope_string Space-separated scopes (per OAuth 2.1 §3.3).
	 * @return array<int, string>
	 */
	public static function parse( string $scope_string ): array {
		$out = [];
		foreach ( preg_split( '/\s+/', trim( $scope_string ) ) ?: [] as $s ) {
			$s = trim( $s );
			if ( '' === $s ) {
				continue;
			}
			if ( in_array( $s, self::ALL, true ) ) {
				$out[] = $s;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param array<int, string> $scopes
	 */
	public static function serialize( array $scopes ): string {
		return implode( ' ', array_values( array_unique( $scopes ) ) );
	}

	/**
	 * Whether the granted scope set satisfies the required one.
	 *
	 * @param array<int, string>|string $granted
	 * @param array<int, string>|string $required
	 */
	public static function contains( $granted, $required ): bool {
		$granted_arr  = is_array( $granted ) ? $granted : self::parse( (string) $granted );
		$required_arr = is_array( $required ) ? $required : self::parse( (string) $required );
		foreach ( $required_arr as $needle ) {
			if ( ! in_array( $needle, $granted_arr, true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Maps an ability's annotations to the minimum scope it requires.
	 *
	 * @param array<string, mixed> $annotations
	 */
	public static function for_annotations( array $annotations ): string {
		if ( ! empty( $annotations['destructive'] ) ) {
			return self::DESTRUCTIVE;
		}
		if ( empty( $annotations['readonly'] ) ) {
			return self::WRITE;
		}
		return self::READ;
	}
}
