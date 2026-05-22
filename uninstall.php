<?php
/**
 * Uninstall script for Webinhood MCP Server.
 *
 * @package Tropk\Mcp
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$audit_table = $wpdb->prefix . 'mcp_audit_log';
$wpdb->query( "DROP TABLE IF EXISTS `{$audit_table}`" );

foreach ( [ 'mcp_oauth_tokens', 'mcp_oauth_codes', 'mcp_oauth_clients' ] as $oauth_table ) {
	$name = $wpdb->prefix . $oauth_table;
	$wpdb->query( "DROP TABLE IF EXISTS `{$name}`" );
}

delete_option( 'webinhood_mcp_db_version' );
delete_option( 'webinhood_mcp_audit_db_version' );
delete_option( 'webinhood_mcp_oauth_db_version' );
delete_option( 'webinhood_mcp_allowed_origins' );
delete_option( 'webinhood_mcp_rewrites_flushed' );
delete_option( 'webinhood_mcp_well_known_state' );
flush_rewrite_rules( false );

foreach ( [ 'oauth-protected-resource', 'oauth-authorization-server' ] as $f ) {
	$path = ABSPATH . '.well-known/' . $f;
	if ( file_exists( $path ) ) {
		@unlink( $path );
	}
}

$role = get_role( 'administrator' );
if ( $role ) {
	$role->remove_cap( 'mcp_invoke_destructive_tools' );
}

wp_clear_scheduled_hook( 'webinhood_mcp_audit_cleanup' );
wp_clear_scheduled_hook( 'webinhood_mcp_oauth_cleanup' );
