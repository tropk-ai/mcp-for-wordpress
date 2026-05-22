<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth;

/**
 * DDL for the OAuth 2.1 storage. Three tables: clients (from DCR or
 * pre-registered), single-use authorization codes (PKCE S256), and
 * paired access + refresh tokens with rotation lineage.
 */
final class Tables {

	public const DB_VERSION = '1';

	public static function clients(): string {
		global $wpdb;
		return $wpdb->prefix . 'mcp_oauth_clients';
	}

	public static function codes(): string {
		global $wpdb;
		return $wpdb->prefix . 'mcp_oauth_codes';
	}

	public static function tokens(): string {
		global $wpdb;
		return $wpdb->prefix . 'mcp_oauth_tokens';
	}

	public static function install(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$clients = self::clients();
		$codes   = self::codes();
		$tokens  = self::tokens();

		$sql = "CREATE TABLE {$clients} (
			client_id VARCHAR(64) NOT NULL,
			client_name VARCHAR(255) NOT NULL,
			client_secret_hash CHAR(64) NULL,
			client_type VARCHAR(20) NOT NULL DEFAULT 'public',
			redirect_uris LONGTEXT NOT NULL,
			grant_types VARCHAR(255) NOT NULL DEFAULT 'authorization_code,refresh_token',
			scope VARCHAR(255) NOT NULL DEFAULT 'mcp:read',
			metadata LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (client_id),
			KEY client_name (client_name(100))
		) {$charset};
		CREATE TABLE {$codes} (
			code_hash CHAR(64) NOT NULL,
			client_id VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			redirect_uri TEXT NOT NULL,
			code_challenge VARCHAR(128) NOT NULL,
			code_challenge_method VARCHAR(10) NOT NULL DEFAULT 'S256',
			scope VARCHAR(255) NOT NULL,
			resource VARCHAR(255) NULL,
			expires_at DATETIME NOT NULL,
			used TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (code_hash),
			KEY client_id (client_id),
			KEY expires_at (expires_at)
		) {$charset};
		CREATE TABLE {$tokens} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			access_token_hash CHAR(64) NOT NULL,
			refresh_token_hash CHAR(64) NOT NULL,
			client_id VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			scope VARCHAR(255) NOT NULL,
			resource VARCHAR(255) NULL,
			access_expires_at DATETIME NOT NULL,
			refresh_expires_at DATETIME NOT NULL,
			revoked TINYINT(1) NOT NULL DEFAULT 0,
			last_used_at DATETIME NULL,
			rotated_from CHAR(64) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY access_token_hash (access_token_hash),
			UNIQUE KEY refresh_token_hash (refresh_token_hash),
			KEY client_id (client_id),
			KEY user_id (user_id),
			KEY access_expires_at (access_expires_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'tropk_mcp_oauth_db_version', self::DB_VERSION, false );
	}

	public static function drop(): void {
		global $wpdb;
		foreach ( [ self::tokens(), self::codes(), self::clients() ] as $table ) {
			$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
		}
		delete_option( 'tropk_mcp_oauth_db_version' );
	}
}
