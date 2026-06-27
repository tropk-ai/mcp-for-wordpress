<?php
/**
 * Plugin Name:       MCP for WP, Elementor and more by Tropk.ai
 * Plugin URI:        https://github.com/tropk-ai/mcp-for-wordpress
 * Description:       Turns any WordPress site into a Model Context Protocol (MCP) server for Claude.ai, ChatGPT and other AI assistants. Ships 450+ tools across content, Elementor, ACF, Rank Math, WooCommerce, Gutenberg / FSE, cron, performance, security and roles.
 * Version:           0.5.4
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            Tropk.ai
 * Author URI:        https://tropk.ai
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       mcp-for-wordpress
 * Domain Path:       /languages
 *
 * @package Tropk\Mcp
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TROPK_MCP_VERSION', '0.5.4' );
define( 'TROPK_MCP_FILE', __FILE__ );
define( 'TROPK_MCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'TROPK_MCP_URL', plugin_dir_url( __FILE__ ) );
define( 'TROPK_MCP_MIN_WP', '6.9' );
define( 'TROPK_MCP_MIN_PHP', '8.1' );

// Backwards-compat aliases so any old code referencing the previous
// constants still works after the rename to MCP for WP.
if ( ! defined( 'WEBINHOOD_MCP_VERSION' ) ) {
	define( 'WEBINHOOD_MCP_VERSION', TROPK_MCP_VERSION );
}
if ( ! defined( 'WEBINHOOD_MCP_DIR' ) ) {
	define( 'WEBINHOOD_MCP_DIR', TROPK_MCP_DIR );
}
if ( ! defined( 'WEBINHOOD_MCP_FILE' ) ) {
	define( 'WEBINHOOD_MCP_FILE', TROPK_MCP_FILE );
}
if ( ! defined( 'WEBINHOOD_MCP_URL' ) ) {
	define( 'WEBINHOOD_MCP_URL', TROPK_MCP_URL );
}

$tropk_mcp_autoloader = TROPK_MCP_DIR . 'vendor/autoload_packages.php';
if ( ! file_exists( $tropk_mcp_autoloader ) ) {
	$tropk_mcp_autoloader = TROPK_MCP_DIR . 'vendor/autoload.php';
}

if ( ! file_exists( $tropk_mcp_autoloader ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'MCP for WP by Tropk.ai: Composer dependencies are missing. Run "composer install" inside the plugin directory.', 'mcp-for-wordpress' );
			echo '</p></div>';
		}
	);
	return;
}

require_once $tropk_mcp_autoloader;

// Repopulate the Authorization header into PHP_AUTH_USER/PHP_AUTH_PW before
// WordPress's auth chain runs. Many hosts (Apache without SetEnvIf, certain
// PHP-FPM/cPanel defaults) strip the Authorization header from $_SERVER,
// breaking Application Passwords + Bearer tokens silently. Must run as
// early as possible, before plugins_loaded.
\Tropk\Mcp\Auth\AuthorizationHeaderShim::bootstrap();

// Intercept /.well-known/oauth-* and openid-configuration before WordPress
// has a chance to canonical-redirect or 404 them. On hosts where nginx
// proxies /.well-known/ paths through to WordPress (Hostinger HCDN,
// KingHost-style nginx defaults, some shared LiteSpeed configs), WP's
// `redirect_canonical` fires before our `parse_request` listener and turns
// /.well-known/openid-configuration into a 301 to `/` (`x-redirect-by:
// WordPress`), breaking OAuth discovery for Claude.ai / ChatGPT / Cursor.
// Hooking at plugins_loaded:1 runs before init / parse_request /
// template_redirect, so this catches the request before any routing
// decision and serves the JSON directly.
\Tropk\Mcp\OAuth\Endpoints\MetadataEndpoints::boot_well_known_early();

add_action(
	'init',
	static function (): void {
		load_plugin_textdomain( 'mcp-for-wordpress', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( version_compare( PHP_VERSION, TROPK_MCP_MIN_PHP, '<' ) ) {
			return;
		}

		global $wp_version;
		if ( isset( $wp_version ) && version_compare( $wp_version, TROPK_MCP_MIN_WP, '<' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>';
					printf(
						/* translators: %s: minimum WordPress version */
						esc_html__( 'MCP for WP by Tropk.ai requires WordPress %s or newer (Abilities API).', 'mcp-for-wordpress' ),
						esc_html( TROPK_MCP_MIN_WP )
					);
					echo '</p></div>';
				}
			);
			return;
		}

		if ( ! function_exists( 'wp_register_ability' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'MCP for WP by Tropk.ai: the Abilities API is unavailable. Make sure WordPress 6.9 or newer is installed.', 'mcp-for-wordpress' );
					echo '</p></div>';
				}
			);
			return;
		}

		try {
			( new \Tropk\Mcp\Plugin() )->boot();
		} catch ( \Throwable $e ) {
			// Last-resort guard. If the plugin can't even boot we surface
			// a single admin notice instead of fatalling the entire site.
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( '[mcp-for-wordpress] boot failed: ' . $e->getMessage() );
			}
			add_action(
				'admin_notices',
				static function () use ( $e ): void {
					echo '<div class="notice notice-error"><p><strong>MCP for WP by Tropk.ai:</strong> boot failed — ';
					echo esc_html( $e->getMessage() );
					echo '. Set <code>define( \'TROPK_MCP_DISABLE_EXTRAS\', true );</code> in wp-config.php to isolate the failure.</p></div>';
				}
			);
		}
	},
	5
);

register_activation_hook( __FILE__, static function (): void {
	require_once TROPK_MCP_DIR . 'src/Audit/AuditTable.php';
	require_once TROPK_MCP_DIR . 'src/OAuth/Tables.php';
	require_once TROPK_MCP_DIR . 'src/Admin/OnboardingPage.php';
	\Tropk\Mcp\Audit\AuditTable::install();
	\Tropk\Mcp\OAuth\Tables::install();

	if ( ! get_option( 'tropk_mcp_db_version' ) ) {
		add_option( 'tropk_mcp_db_version', TROPK_MCP_VERSION );
	}

	$role = get_role( 'administrator' );
	if ( $role && ! $role->has_cap( 'mcp_invoke_destructive_tools' ) ) {
		$role->add_cap( 'mcp_invoke_destructive_tools' );
	}

	if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		\Tropk\Mcp\Admin\OnboardingPage::on_activation();
	}
} );

register_deactivation_hook( __FILE__, static function (): void {
	wp_clear_scheduled_hook( 'tropk_mcp_audit_cleanup' );
	wp_clear_scheduled_hook( 'tropk_mcp_oauth_cleanup' );
	wp_clear_scheduled_hook( 'webinhood_mcp_audit_cleanup' );
	wp_clear_scheduled_hook( 'webinhood_mcp_oauth_cleanup' );
} );
