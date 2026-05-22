<?php
declare(strict_types=1);

namespace Tropk\Mcp\Auth;

/**
 * Many hosts (Apache without `SetEnvIf Authorization`, some PHP-FPM
 * setups, certain managed WP hosts, cPanel default rewrites) drop the
 * Authorization header before WordPress sees it. The symptom is silent:
 * Application Passwords and Bearer tokens stop working, but the only
 * response the client sees is 401 with no explanation.
 *
 * This shim repopulates PHP_AUTH_USER/PHP_AUTH_PW from any source that
 * still carries the header (apache_request_headers, getallheaders,
 * REDIRECT_HTTP_AUTHORIZATION) before WP's own auth chain runs, so
 * both Basic (Application Passwords) and Bearer (our OAuth tokens)
 * always have a chance to authenticate.
 */
final class AuthorizationHeaderShim {

	public static function bootstrap(): void {
		$header = self::extract_authorization_header();
		if ( '' === $header ) {
			return;
		}

		if ( empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$_SERVER['HTTP_AUTHORIZATION'] = $header;
		}

		if ( 0 === stripos( $header, 'Basic ' ) ) {
			if ( ! isset( $_SERVER['PHP_AUTH_USER'] ) || ! isset( $_SERVER['PHP_AUTH_PW'] ) ) {
				$decoded = base64_decode( substr( $header, 6 ), true );
				if ( is_string( $decoded ) && false !== strpos( $decoded, ':' ) ) {
					[ $user, $pass ]              = explode( ':', $decoded, 2 );
					$_SERVER['PHP_AUTH_USER']     = $user;
					$_SERVER['PHP_AUTH_PW']       = $pass;
				}
			}
		}
	}

	public static function status(): array {
		$header = self::extract_authorization_header();
		return [
			'authorization_header_present'   => '' !== $header,
			'authorization_header_scheme'    => '' === $header ? null : strtolower( strtok( $header, ' ' ) ?: '' ),
			'php_auth_user_present'          => isset( $_SERVER['PHP_AUTH_USER'] ),
			'http_authorization_present'     => isset( $_SERVER['HTTP_AUTHORIZATION'] ),
			'redirect_http_authorization_present' => isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ),
			'getallheaders_available'        => function_exists( 'getallheaders' ),
			'apache_request_headers_available' => function_exists( 'apache_request_headers' ),
		];
	}

	private static function extract_authorization_header(): string {
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return (string) $_SERVER['HTTP_AUTHORIZATION'];
		}
		if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
		}
		if ( ! empty( $_SERVER['HTTP_X_AUTHORIZATION'] ) ) {
			return (string) $_SERVER['HTTP_X_AUTHORIZATION'];
		}

		if ( function_exists( 'apache_request_headers' ) ) {
			$headers = apache_request_headers();
			if ( is_array( $headers ) ) {
				foreach ( $headers as $name => $value ) {
					if ( 0 === strcasecmp( (string) $name, 'authorization' ) ) {
						return (string) $value;
					}
				}
			}
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			if ( is_array( $headers ) ) {
				foreach ( $headers as $name => $value ) {
					if ( 0 === strcasecmp( (string) $name, 'authorization' ) ) {
						return (string) $value;
					}
				}
			}
		}

		return '';
	}
}
