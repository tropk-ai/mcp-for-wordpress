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
		//
		// We also bridge the Abilities API initialisation actions onto the
		// adapter's public registration methods. mcp-adapter v0.5.x adds
		// its hooks for the three core abilities (discover-abilities,
		// get-ability-info, execute-ability) INSIDE maybe_create_default_server(),
		// which runs from McpAdapter::init() at rest_api_init:15. By that
		// point WordPress 6.9+/7.0 (which ships the Abilities API in core)
		// has already fired `wp_abilities_api_init` during the `init` action,
		// so the add_action calls land after the action has fired and the
		// three abilities never register. McpComponentRegistry then logs
		// "WordPress ability '…' does not exist" for each, MCP discovery
		// breaks, and connecting clients (Claude.ai, ChatGPT, Cursor, …)
		// surface a generic "Não foi possível registrar" error before the
		// OAuth /authorize hop is even reached, because the broker can't
		// enumerate the server's tool surface and aborts client-side.
		//
		// We bridge BOTH name variants because: (a) WP core fires the
		// prefixed `wp_abilities_api_*` form (confirmed via did_action()
		// probe on WP 7.0); (b) the vendored wordpress/abilities-api
		// package — which only loads if WP core lacks WP_Abilities_Registry
		// — fires the unprefixed `abilities_api_*` form. Double-hook keeps
		// us correct against either runtime. AbilityRegistrar already uses
		// this double-hook pattern for our own tropk-core/* abilities
		// (see AbilityRegistrar::register()) — this mirrors it for the
		// mcp-adapter upstream defaults.
		add_action(
			'plugins_loaded',
			static function (): void {
				if ( ! class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) ) {
					return;
				}
				$adapter = \WP\MCP\Core\McpAdapter::instance();
				if ( ! apply_filters( 'mcp_adapter_create_default_server', true ) ) {
					return;
				}
				if ( method_exists( $adapter, 'register_default_category' ) ) {
					add_action( 'wp_abilities_api_categories_init', [ $adapter, 'register_default_category' ] );
					add_action( 'abilities_api_categories_init',    [ $adapter, 'register_default_category' ] );
				}
				if ( method_exists( $adapter, 'register_default_abilities' ) ) {
					add_action( 'wp_abilities_api_init', [ $adapter, 'register_default_abilities' ] );
					add_action( 'abilities_api_init',    [ $adapter, 'register_default_abilities' ] );
					// Belt-and-braces: if either init action already fired
					// before our add_action landed (another plugin probed
					// wp_get_ability() during plugins_loaded < 20 and lazy-
					// instantiated the registry), the action listeners above
					// will never run. Register synchronously at init:5, which
					// is after AbilityRegistrar's init:1 registry warm-up but
					// before McpAdapter's rest_api_init:15. Static guard plus
					// wp_get_ability() null check keep it idempotent across
					// multiple init firings (CLI, REST, front-end all share
					// this path).
					add_action(
						'init',
						static function () use ( $adapter ): void {
							static $done = false;
							if ( $done || ! function_exists( 'wp_get_ability' ) ) {
								return;
							}
							$done = true;
							$fired = did_action( 'wp_abilities_api_init' ) > 0
								|| did_action( 'abilities_api_init' ) > 0;
							if ( ! $fired ) {
								return;
							}
							if ( null !== wp_get_ability( 'mcp-adapter/discover-abilities' ) ) {
								return;
							}
							$adapter->register_default_abilities();
						},
						5
					);
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
		$desc      = $this->build_server_instructions( $site_host );

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
	 * The `description` we pass to create_server() is surfaced by the
	 * mcp-adapter as the MCP `instructions` field on the initialize
	 * response — every connecting agent (Claude.ai, ChatGPT, Cursor,
	 * Claude Desktop, mcp-inspector …) reads it before its first tool call.
	 * Keep this terse: the agent gets the V4 atomic workflow up front and
	 * the per-tool descriptions cover the rest.
	 */
	private function build_server_instructions( string $site_host ): string {
		$where = '' !== $site_host ? $site_host : (string) home_url();
		return sprintf(
			/* translators: %s: site host */
			__( <<<'TXT'
WordPress MCP server for %s, powered by Tropk.ai. Exposes 450+ tools across content, SEO, the page-builders (Elementor + Gutenberg/FSE), WooCommerce, cron, performance and security.

## Elementor V4 (atomic) — schema-first workflow

V4 widgets are schema-driven and every persisted value is a typed envelope:
  { "$$type": "<key>", "value": <scalar|object|array> }
(only the `classes` prop value is a plain string[] — every other typed value uses the envelope).

Don't hand-roll widget settings. Always:
  1. Call `elementor-get-atomic-schema` (or `elementor-get-style-schema` / `elementor-list-prop-types`) FIRST to see which keys exist and which $$type each one expects.
  2. Build settings/styles as native JSON objects with that exact shape.
  3. Use the V4 tools below — they call Elementor's own repositories so the editor stays in sync.

Key V4 tools:
- Elements:  `elementor-add-atomic-element` (one builder for any e-* widget AND containers e-div-block / e-flexbox / e-grid; supports nested children), `elementor-get-atomic-settings`, `elementor-set-atomic-settings`.
- Local styles (CSS scoped to one element, with breakpoint+state variants — hover, focus, tablet, …): `elementor-get-element-styles`, `elementor-set-element-style`, `elementor-remove-element-style`.
- Global Classes (reuse layer — edit once, every element using the class updates): `elementor-list-global-classes`, `elementor-get-global-class`, `elementor-create-global-class`, `elementor-update-global-class`, `elementor-delete-global-class`, `elementor-reorder-global-classes`, `elementor-set-global-class-props` (merge into one variant), `elementor-apply-global-class-to-element`.
- Design-system Variables (named colors + fonts, cascade to every reference): `elementor-list-variables`, `elementor-create-variable`, `elementor-update-variable`, `elementor-delete-variable`, `elementor-restore-variable`. Variable prop-types: `global-color-variable`, `global-font-variable`.
- Schema: `elementor-get-atomic-schema`, `elementor-get-style-schema`, `elementor-list-prop-types`.

Persisted JSON shapes (so you know what you're producing):
- widget atomic node: `{ id, elType:"widget", widgetType:"e-heading", settings:{…typed…}, styles:{…}, editor_settings:{}, version:"0.0", elements:[] }`
- container atomic node: `{ id, elType:"e-div-block"|"e-flexbox"|"e-grid", settings, styles, editor_settings, version, elements:[ children… ] }`
- style definition (entry in element.styles): `{ id:"e-<elId>-<hash>", type:"class", label, variants:[{ meta:{ breakpoint?:string|null, state?:string|null }, props:{<css-prop>:<typed value>} }] }`

Compose nested typed props (dimensions is four sides, each a `size`, whose value is `{ unit, size }`):
  { "$$type":"dimensions", "value":{ "block-start":{"$$type":"size","value":{"size":12,"unit":"px"}}, … } }

Safety: destructive Elementor tools snapshot the post automatically. Global Classes and Variables modify the active Kit, so they affect every page that references them — confirm with the user before bulk renames/deletes.
TXT
				, 'mcp-for-wordpress' ),
			$where
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
