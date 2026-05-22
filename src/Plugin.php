<?php
declare(strict_types=1);

namespace Tropk\Mcp;

use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Admin\OnboardingPage;
use Tropk\Mcp\Audit\AuditLogger;
use Tropk\Mcp\Auth\ConfusedDeputyGuard;
use Tropk\Mcp\Diagnostics\DiagnosticEndpoint;
use Tropk\Mcp\Extras\Loader as ExtrasLoader;
use Tropk\Mcp\OAuth\Bootstrap as OAuthBootstrap;
use Tropk\Mcp\Security\CorsHandler;
use Tropk\Mcp\Security\OriginGuard;
use Tropk\Mcp\Security\RateLimiter;
use Tropk\Mcp\Server\McpServerBootstrap;

final class Plugin {

	public function boot(): void {
		// Core services first. The OnboardingPage is registered up-front
		// (before any potentially-failing component) so the admin settings
		// UI stays reachable even if the optional Extras tier later blows up.
		( new CorsHandler() )->register();
		( new ConfusedDeputyGuard() )->register();
		( new OriginGuard() )->register();
		( new RateLimiter() )->register();
		( new AuditLogger() )->register();
		( new AbilityRegistrar() )->register();
		( new McpServerBootstrap() )->register();
		( new OAuthBootstrap() )->register();
		( new DiagnosticEndpoint() )->register();

		if ( is_admin() ) {
			( new OnboardingPage() )->register();
		}

		// Optional, larger ability tier (WooCommerce, Gutenberg, theme/FSE,
		// cron, performance, security, roles, database, shortcodes, media,
		// bulk, terms). Loader::boot schedules a deferred plugins_loaded
		// hook; failures inside the deferred callback are isolated per-file
		// by the loader itself.
		try {
			( new ExtrasLoader() )->boot();
		} catch ( \Throwable $e ) {
			$this->log_optional_failure( 'ExtrasLoader', $e );
		}
	}

	private function log_optional_failure( string $component, \Throwable $e ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[mcp-for-wordpress] %s failed: %s', $component, $e->getMessage() ) );
		}
	}
}
