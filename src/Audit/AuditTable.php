<?php
declare(strict_types=1);

namespace Tropk\Mcp\Audit;

final class AuditTable {

	public const DB_VERSION = '1';

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'mcp_audit_log';
	}

	public static function install(): void {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			session_id VARCHAR(128) NOT NULL DEFAULT '',
			tool_name VARCHAR(190) NOT NULL DEFAULT '',
			arguments_json LONGTEXT NULL,
			result_summary TEXT NULL,
			result_hash CHAR(64) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'ok',
			duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
			ip VARCHAR(45) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY tool_name (tool_name),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'tropk_mcp_audit_db_version', self::DB_VERSION, false );
	}
}
