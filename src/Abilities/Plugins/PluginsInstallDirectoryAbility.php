<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Plugins;

use Tropk\Mcp\Abilities\AbstractAbility;

final class PluginsInstallDirectoryAbility extends AbstractAbility {
	public function slug(): string { return 'plugins-install-directory'; }
	protected function meta(): array { return [
		'label' => __( 'Install a plugin from wordpress.org', 'mcp-for-wordpress' ),
		'description' => __( 'Installs a plugin by its wordpress.org slug. Optionally activates it after install.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'slug' ],
		'properties'           => [
			'slug'     => [ 'type' => 'string', 'minLength' => 1 ],
			'activate' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'installed' => [ 'type' => 'boolean' ], 'activated' => [ 'type' => 'boolean' ], 'file' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'install_plugins' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$api = plugins_api( 'plugin_information', [ 'slug' => (string) $input['slug'], 'fields' => [ 'sections' => false ] ] );
		if ( is_wp_error( $api ) ) {
			throw new \RuntimeException( $api->get_error_message() );
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$res      = $upgrader->install( $api->download_link );
		if ( is_wp_error( $res ) ) {
			throw new \RuntimeException( $res->get_error_message() );
		}
		$file = $upgrader->plugin_info();
		$activated = false;
		if ( ! empty( $input['activate'] ) && $file ) {
			$act = activate_plugin( (string) $file );
			$activated = ! is_wp_error( $act );
		}
		return [ 'installed' => true, 'activated' => $activated, 'file' => $file ];
	}
}
