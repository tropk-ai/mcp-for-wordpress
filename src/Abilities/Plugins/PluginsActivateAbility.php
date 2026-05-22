<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Plugins;

use Tropk\Mcp\Abilities\AbstractAbility;

final class PluginsActivateAbility extends AbstractAbility {
	public function slug(): string { return 'plugins-activate'; }
	protected function meta(): array { return [
		'label' => __( 'Activate a plugin', 'mcp-for-wordpress' ),
		'description' => __( 'Activates an installed plugin by its plugin file (e.g. "akismet/akismet.php").', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'file' ],
		'properties'           => [ 'file' => [ 'type' => 'string' ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'activated' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'activate_plugins' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$res = activate_plugin( (string) $input['file'] );
		if ( is_wp_error( $res ) ) {
			throw new \RuntimeException( $res->get_error_message() );
		}
		return [ 'activated' => true ];
	}
}
