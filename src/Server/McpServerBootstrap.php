<?php
declare(strict_types=1);

namespace Tropk\Mcp\Server;

use Tropk\Mcp\Abilities\AbilityRegistrar;

/**
 * Boots the MCP server via wordpress/mcp-adapter, exposing the registered
 * abilities at /wp-json/tropk-mcp/v1/mcp using the Streamable HTTP
 * transport. SSE-only is intentionally not exposed (deprecated since the
 * 2025-03-26 revision of the MCP spec).
 */
final class McpServerBootstrap {

	public const SERVER_ID    = 'tropk-mcp-server';
	public const REST_NS      = 'tropk-mcp/v1';
	public const REST_ROUTE   = 'mcp';

	public function register(): void {
		// Priority 20: msrbuilds and the WP Abilities API both hook
		// `wp_abilities_api_init` to perform their registrations. Running
		// our `create_server` at 20 (after the default 10) guarantees
		// `WP_Abilities_Registry::get_all_registered()` has been populated
		// by every vendored source before we aggregate it.
		add_action( 'mcp_adapter_init', [ $this, 'create_server' ], 20, 1 );

		// McpAdapter is a lazy singleton; touch it so its rest_api_init hook
		// fires and the mcp_adapter_init action eventually dispatches.
		add_action(
			'plugins_loaded',
			static function (): void {
				if ( class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) ) {
					\WP\MCP\Core\McpAdapter::instance();
				}
			},
			20
		);
	}

	public function create_server( $adapter ): void {
		if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) ) {
			return;
		}

		$transports = $this->resolve_transports();
		if ( [] === $transports ) {
			return;
		}

		$site_name = (string) get_bloginfo( 'name' );
		$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$label     = '' !== $site_name
			? sprintf( __( 'Wordpress MCP by Tropk.ai — %s', 'mcp-for-wordpress' ), $site_name )
			: __( 'Wordpress MCP by Tropk.ai', 'mcp-for-wordpress' );
		$desc      = sprintf(
			/* translators: %s: site host */
			__( 'WordPress MCP server for %s, powered by Tropk.ai. Exposes 450+ tools — content, SEO, page-builder, WooCommerce — to any MCP-compatible AI assistant.', 'mcp-for-wordpress' ),
			'' !== $site_host ? $site_host : home_url()
		);

		$adapter->create_server(
			self::SERVER_ID,
			self::REST_NS,
			self::REST_ROUTE,
			$label,
			$desc,
			TROPK_MCP_VERSION,
			$transports,
			null,
			null,
			$this->aggregate_ability_names()
		);
	}

	/**
	 * Aggregates every ability currently registered with WP_Abilities_Registry,
	 * not just the ones declared by tropk-mcp itself. This is what makes
	 * the single `/wp-json/tropk-mcp/v1/mcp` endpoint expose the full
	 * ~300-tool surface (vendored bjornfix + msrbuilds + angie-acf + our
	 * own tropk-mcp/* and Extras/*).
	 *
	 * An opt-in filter (`tropk_mcp_ability_names`) lets ops curate the
	 * set without modifying PHP.
	 *
	 * @return array<int, string>
	 */
	private function aggregate_ability_names(): array {
		$names = [];
		if ( class_exists( '\\WP_Abilities_Registry' ) ) {
			$reg = \WP_Abilities_Registry::get_instance();
			if ( method_exists( $reg, 'get_all_registered' ) ) {
				$all   = $reg->get_all_registered();
				$names = array_keys( is_array( $all ) ? $all : [] );
			}
		}
		if ( empty( $names ) ) {
			$names = AbilityRegistrar::registered_ability_names();
		}
		$names = array_values( array_unique( $names ) );
		return (array) apply_filters( 'tropk_mcp_ability_names', $names );
	}

	/**
	 * @return array<int, class-string>
	 */
	private function resolve_transports(): array {
		$candidates = [
			'WP\\MCP\\Transport\\HttpTransport',
			'WordPress\\MCP\\Transport\\Http\\StreamableHttpTransport',
			'WP\\MCP\\Transport\\Http\\StreamableHttpTransport',
			'WordPress\\McpAdapter\\Transport\\Http\\HttpTransport',
		];

		$resolved = [];
		foreach ( $candidates as $class ) {
			if ( class_exists( $class ) ) {
				$resolved[] = $class;
				break;
			}
		}

		return (array) apply_filters( 'tropk_mcp_transports', $resolved );
	}
}
