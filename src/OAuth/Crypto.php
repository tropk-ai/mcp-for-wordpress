<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth;

/**
 * Cryptographic primitives for the OAuth flow: opaque token generation,
 * PKCE S256 verification, and constant-time hash comparison. Token
 * material is generated via random_bytes (CSPRNG); never via uniqid or
 * mt_rand. Tokens are stored only as their SHA-256 hash.
 */
final class Crypto {

	public static function new_token( int $bytes = 32 ): string {
		return self::base64url( random_bytes( max( 16, $bytes ) ) );
	}

	public static function new_client_id(): string {
		return 'mcp_' . self::base64url( random_bytes( 18 ) );
	}

	public static function hash( string $value ): string {
		return hash( 'sha256', $value );
	}

	public static function safe_equals( string $a, string $b ): bool {
		return hash_equals( $a, $b );
	}

	public static function base64url( string $bytes ): string {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}

	/**
	 * Verify PKCE per RFC 7636. S256 only; plain is rejected per
	 * MCP 2025-11-25 / OAuth 2.1 guidance.
	 */
	public static function verify_pkce( string $code_verifier, string $code_challenge, string $method = 'S256' ): bool {
		if ( 'S256' !== strtoupper( $method ) ) {
			return false;
		}
		if ( ! preg_match( '/^[A-Za-z0-9\-._~]{43,128}$/', $code_verifier ) ) {
			return false;
		}
		$expected = self::base64url( hash( 'sha256', $code_verifier, true ) );
		return hash_equals( $expected, $code_challenge );
	}
}
