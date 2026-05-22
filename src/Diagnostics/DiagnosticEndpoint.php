<?php
declare(strict_types=1);

namespace Tropk\Mcp\Diagnostics;

use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Auth\AuthorizationHeaderShim;
use Tropk\Mcp\OAuth\Endpoints\MetadataEndpoints;
use Tropk\Mcp\OAuth\Endpoints\WellKnownStaticFiles;
use Tropk\Mcp\Security\OriginGuard;

/**
 * GET /wp-json/tropk-mcp/v1/diagnostic
 *
 * Lets the operator (or a curl from anywhere) verify that every moving
 * part of the MCP + OAuth discovery is wired up. No authentication is
 * required because none of the fields leak privileged data — they only
 * reflect the public surface a remote MCP client would see.
 */
final class DiagnosticEndpoint {

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	public function register_route(): void {
		register_rest_route(
			'tropk-mcp/v1',
			'/diagnostic',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			'tropk-mcp/v1',
			'/whoami',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'whoami' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function whoami(): \WP_REST_Response {
		$user_id = get_current_user_id();
		$user    = $user_id ? get_userdata( $user_id ) : null;

		$payload = [
			'authenticated' => $user_id > 0,
			'user_id'       => $user_id,
			'user_login'    => $user instanceof \WP_User ? $user->user_login : null,
			'user_email'    => $user instanceof \WP_User ? $user->user_email : null,
			'roles'         => $user instanceof \WP_User ? array_values( $user->roles ) : [],
			'can_read'              => current_user_can( 'read' ),
			'can_edit_posts'        => current_user_can( 'edit_posts' ),
			'can_invoke_destructive' => current_user_can( 'mcp_invoke_destructive_tools' ),
			'authorization_header'  => AuthorizationHeaderShim::status(),
		];

		$response = new \WP_REST_Response( $payload, 200 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	public function handle(): \WP_REST_Response {
		$mcp_endpoint = rest_url( 'tropk-mcp/v1/mcp' );

		// Force adapter + abilities to initialize so we can probe live state.
		if ( class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) ) {
			$adapter = \WP\MCP\Core\McpAdapter::instance();
			if ( method_exists( $adapter, 'init' ) ) {
				$adapter->init();
			}
		}

		$rest_routes        = rest_get_server()->get_routes();
		$mcp_route_present  = array_key_exists( '/tropk-mcp/v1/mcp', $rest_routes );
		$oauth_paths        = [
			'/tropk-mcp/v1/oauth/authorize',
			'/tropk-mcp/v1/oauth/token',
			'/tropk-mcp/v1/oauth/register',
			'/tropk-mcp/v1/oauth/revoke',
			'/tropk-mcp/v1' . MetadataEndpoints::REST_PRM_PATH,
			'/tropk-mcp/v1' . MetadataEndpoints::REST_AS_PATH,
		];
		$oauth_routes_present = [];
		foreach ( $oauth_paths as $path ) {
			$oauth_routes_present[ $path ] = array_key_exists( $path, $rest_routes );
		}

		$payload = [
			'plugin'         => [
				'name'    => 'MCP for WP by Tropk.ai',
				'version' => defined( 'TROPK_MCP_VERSION' ) ? TROPK_MCP_VERSION : 'unknown',
			],
			'site'           => [
				'name'  => (string) get_bloginfo( 'name' ),
				'home'  => home_url( '/' ),
				'rest'  => rest_url(),
				'https' => is_ssl(),
			],
			'mcp'            => [
				'endpoint'        => $mcp_endpoint,
				'route_registered' => $mcp_route_present,
				'transport_class' => $this->resolve_transport_class(),
				'adapter_initialized' => $this->adapter_initialized(),
				'abilities_count' => count( AbilityRegistrar::registered_ability_names() ),
				'abilities_registered_live' => $this->live_ability_count(),
				'tools_registered_live'     => $this->live_tool_count(),
				'first_ability_check'       => $this->first_ability_check(),
				'abilities_api_state'       => $this->abilities_api_state(),
				'per_ability_status'        => $this->each_ability_check(),
			],
			'oauth'          => [
				'protected_resource_metadata' => MetadataEndpoints::rest_prm_url(),
				'authorization_server_metadata' => MetadataEndpoints::rest_as_url(),
				'routes' => $oauth_routes_present,
			],
			'well_known'     => [
				'note' => 'These URLs are what OAuth clients construct from the PRM/AS issuer. Status is probed via wp_remote_get to confirm the host actually serves /.well-known/ paths. If reachable=false, the host blocks /.well-known/ at the web-server layer and the OAuth flow on browser clients (Claude.ai, ChatGPT) will fail.',
				'protected_resource' => $this->probe_well_known( home_url( '/.well-known/oauth-protected-resource' ) ),
				'authorization_server' => $this->probe_well_known( home_url( '/.well-known/oauth-authorization-server' ) ),
				'protected_resource_path_based' => $this->probe_well_known( home_url( '/.well-known/oauth-protected-resource/wp-json/tropk-mcp/v1/mcp' ) ),
				'static_files' => ( new WellKnownStaticFiles() )->status(),
			],
			'cors'           => [
				'allowed_origins' => ( new OriginGuard() )->allowed_origins(),
				'note' => 'Browser-based MCP clients (Claude.ai, ChatGPT) preflight Mcp-Session-Id and MCP-Protocol-Version; this plugin allows them explicitly.',
			],
			'application_passwords_supported' => class_exists( '\\WP_Application_Passwords' ),
			'authorization_header'            => AuthorizationHeaderShim::status(),
			'php_version'    => PHP_VERSION,
			'wp_version'     => $GLOBALS['wp_version'] ?? 'unknown',
			'server_software' => (string) ( $_SERVER['SERVER_SOFTWARE'] ?? 'unknown' ),
			'is_ssl'          => is_ssl(),
		];

		$response = new \WP_REST_Response( $payload, 200 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function probe_well_known( string $url ): array {
		$response = wp_remote_get(
			$url,
			[ 'timeout' => 5, 'redirection' => 2, 'sslverify' => true ]
		);
		if ( is_wp_error( $response ) ) {
			return [ 'url' => $url, 'reachable' => false, 'error' => $response->get_error_message() ];
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = (string) wp_remote_retrieve_body( $response );
		$type   = (string) wp_remote_retrieve_header( $response, 'content-type' );
		$is_json = false !== strpos( strtolower( $type ), 'application/json' ) && is_array( json_decode( $body, true ) );
		return [
			'url'            => $url,
			'reachable'      => 200 === $status,
			'http_status'    => $status,
			'content_type'   => $type,
			'returns_json'   => $is_json,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function abilities_api_state(): array {
		global $wp_filter;
		$reg_class = class_exists( '\\WP_Abilities_Registry' ) ? new \ReflectionClass( '\\WP_Abilities_Registry' ) : null;

		// WP 6.9 core renamed the categories registry class. The Composer
		// abilities-api package uses WP_Abilities_Category_Registry; WP 6.9
		// core uses WP_Ability_Categories_Registry (singular Ability, plural
		// Categories). Prefer whichever is actually loaded so the diagnostic
		// reflects the live state.
		$cat_class_name = class_exists( '\\WP_Ability_Categories_Registry' )
			? '\\WP_Ability_Categories_Registry'
			: ( class_exists( '\\WP_Abilities_Category_Registry' ) ? '\\WP_Abilities_Category_Registry' : null );
		$cat_class = null !== $cat_class_name ? new \ReflectionClass( $cat_class_name ) : null;

		$out = [
			'wp_register_ability_exists' => function_exists( 'wp_register_ability' ),
			'wp_get_ability_exists'      => function_exists( 'wp_get_ability' ),
			'did_action_init'                  => (int) did_action( 'abilities_api_init' ),
			'did_action_categories_init'       => (int) did_action( 'abilities_api_categories_init' ),
			'did_action_wp_init'               => (int) did_action( 'wp_abilities_api_init' ),
			'did_action_wp_categories_init'    => (int) did_action( 'wp_abilities_api_categories_init' ),
			'init_callbacks'                   => $this->describe_hook_callbacks( 'abilities_api_init' ),
			'wp_init_callbacks'                => $this->describe_hook_callbacks( 'wp_abilities_api_init' ),
			'categories_init_callbacks'        => $this->describe_hook_callbacks( 'abilities_api_categories_init' ),
			'wp_categories_init_callbacks'     => $this->describe_hook_callbacks( 'wp_abilities_api_categories_init' ),
			'category_registered'              => false,
			'category_registry_class'          => null !== $cat_class ? $cat_class->getName() : null,
			'category_registry_file'           => null !== $cat_class ? $cat_class->getFileName() : null,
			'abilities_registry_class'         => null !== $reg_class ? $reg_class->getName() : null,
			'abilities_registry_file'          => null !== $reg_class ? $reg_class->getFileName() : null,
		];

		if ( null !== $cat_class_name ) {
			$cats = ( $cat_class_name )::get_instance();
			if ( $cats && method_exists( $cats, 'is_registered' ) ) {
				$out['category_registered'] = $cats->is_registered( AbilityRegistrar::CATEGORY );
			}
			if ( $cats && method_exists( $cats, 'get_all_registered' ) ) {
				$out['categories_known'] = array_keys( $cats->get_all_registered() );
			}
		}

		if ( class_exists( '\\WP_Abilities_Registry' ) ) {
			$reg = \WP_Abilities_Registry::get_instance();
			if ( method_exists( $reg, 'get_all_registered' ) ) {
				$out['abilities_known_count'] = count( $reg->get_all_registered() );
				$out['abilities_known_names'] = array_keys( $reg->get_all_registered() );
			}
		}

		return $out;
	}

	/**
	 * @return array<int, string>
	 */
	private function describe_hook_callbacks( string $hook ): array {
		global $wp_filter;
		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return [];
		}
		$out = [];
		$obj = $wp_filter[ $hook ];
		if ( ! is_object( $obj ) || ! isset( $obj->callbacks ) || ! is_array( $obj->callbacks ) ) {
			return [];
		}
		foreach ( $obj->callbacks as $priority => $cbs ) {
			foreach ( (array) $cbs as $cb ) {
				$func = $cb['function'] ?? null;
				if ( is_array( $func ) && count( $func ) === 2 ) {
					$target = is_object( $func[0] ) ? get_class( $func[0] ) : (string) $func[0];
					$out[]  = sprintf( '%d: %s::%s', $priority, $target, (string) $func[1] );
				} elseif ( is_string( $func ) ) {
					$out[] = sprintf( '%d: %s', $priority, $func );
				} elseif ( $func instanceof \Closure ) {
					$out[] = sprintf( '%d: Closure', $priority );
				}
			}
		}
		return $out;
	}

	private function live_ability_count(): int {
		$names = AbilityRegistrar::registered_ability_names();
		$count = 0;
		foreach ( $names as $name ) {
			if ( function_exists( 'wp_get_ability' ) && null !== wp_get_ability( $name ) ) {
				$count++;
			}
		}
		return $count;
	}

	private function live_tool_count(): int {
		if ( ! class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) ) {
			return -1;
		}
		$adapter = \WP\MCP\Core\McpAdapter::instance();
		if ( ! method_exists( $adapter, 'get_server' ) ) {
			return -1;
		}
		$server = $adapter->get_server( 'tropk-mcp-server' );
		if ( ! $server || ! method_exists( $server, 'get_tools' ) ) {
			return -1;
		}
		$tools = $server->get_tools();
		return is_array( $tools ) ? count( $tools ) : -1;
	}

	private function first_ability_check(): array {
		$names = AbilityRegistrar::registered_ability_names();
		if ( [] === $names ) {
			return [ 'name' => null, 'present' => false ];
		}
		$first = $names[0];
		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $first ) : null;
		$out = [
			'name'    => $first,
			'present' => null !== $ability,
			'class'   => is_object( $ability ) ? get_class( $ability ) : null,
		];

		if ( null !== $ability && class_exists( '\\WP\\MCP\\Domain\\Tools\\McpTool' ) ) {
			$tool = \WP\MCP\Domain\Tools\McpTool::fromAbility( $ability );
			if ( is_wp_error( $tool ) ) {
				$out['mcp_tool_error']         = $tool->get_error_code();
				$out['mcp_tool_error_message'] = $tool->get_error_message();
			} else {
				$out['mcp_tool_built'] = true;
				if ( method_exists( $tool, 'get_protocol_dto' ) ) {
					$dto = $tool->get_protocol_dto();
					if ( method_exists( $dto, 'getName' ) ) {
						$out['mcp_tool_name'] = $dto->getName();
					}
				}
			}
		}

		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function each_ability_check(): array {
		$out   = [];
		$names = AbilityRegistrar::registered_ability_names();
		foreach ( $names as $name ) {
			$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
			$entry   = [
				'name'    => $name,
				'present' => null !== $ability,
			];
			if ( null !== $ability && class_exists( '\\WP\\MCP\\Domain\\Tools\\McpTool' ) ) {
				$tool = \WP\MCP\Domain\Tools\McpTool::fromAbility( $ability );
				if ( is_wp_error( $tool ) ) {
					$entry['mcp_tool_error'] = $tool->get_error_code() . ': ' . $tool->get_error_message();
				} else {
					$entry['mcp_tool_built'] = true;
				}
			}
			$out[] = $entry;
		}
		return $out;
	}

	private function resolve_transport_class(): ?string {
		foreach ( [
			'WP\\MCP\\Transport\\HttpTransport',
			'WordPress\\MCP\\Transport\\Http\\StreamableHttpTransport',
		] as $class ) {
			if ( class_exists( $class ) ) {
				return $class;
			}
		}
		return null;
	}

	private function adapter_initialized(): bool {
		if ( ! class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) ) {
			return false;
		}
		$instance = \WP\MCP\Core\McpAdapter::instance();
		if ( ! method_exists( $instance, 'get_servers' ) ) {
			return true;
		}
		$servers = $instance->get_servers();
		return is_array( $servers ) && ! empty( $servers );
	}
}
