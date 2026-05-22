<?php
declare(strict_types=1);

namespace Tropk\Mcp\Extras;

/**
 * Loads tropk-mcp's own extra ability files (procedural style, one
 * domain per file). These were written from scratch in PHP, inspired by
 * the public tool catalog of raheesahmed/wordpress-mcp-server (MIT), to
 * cover WooCommerce, Gutenberg blocks, FSE/theme, cron, performance,
 * security, roles, database, shortcodes, media, bulk ops and term
 * management — none of which the vendored bjornfix/msrbuilds sources
 * provide.
 *
 * Each include is wrapped in try/catch so one bad file can never bring
 * the host plugin down. TROPK_MCP_DISABLE_EXTRAS skips the lot.
 */
final class Loader {

	public function boot(): void {
		if ( defined( 'TROPK_MCP_DISABLE_EXTRAS' ) && constant( 'TROPK_MCP_DISABLE_EXTRAS' ) ) {
			return;
		}

		// Defer to plugins_loaded priority 99 (same as VendorLoader) so
		// every host plugin has finished its bootstrap by the time we
		// register hooks that might touch their globals.
		add_action( 'plugins_loaded', [ $this, 'load_extras' ], 99 );
	}

	public function load_extras(): void {
		$base  = __DIR__;
		$files = [
			'woocommerce.php',
			'blocks.php',
			'theme.php',
			'cron.php',
			'performance.php',
			'security.php',
			'roles.php',
			'database.php',
			'shortcodes.php',
			'media.php',
			'seo.php',
			'bulk.php',
			'terms.php',
		];
		foreach ( $files as $file ) {
			$path = $base . '/' . $file;
			if ( ! file_exists( $path ) ) {
				continue;
			}
			try {
				require_once $path;
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( '[tropk-mcp] Extras "%s" failed to load: %s', $file, $e->getMessage() ) );
				}
			}
		}
	}
}
