<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Plugins;

use Tropk\Mcp\Abilities\AbstractAbility;

final class PluginsDeactivateAbility extends AbstractAbility {
	public function slug(): string { return 'plugins-deactivate'; }
	protected function meta(): array { return [
		'label' => __( 'Deactivate a plugin', 'mcp-for-wordpress' ),
		'description' => __( 'Deactivates an installed plugin by file path.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'file' ],
		'properties'           => [ 'file' => [ 'type' => 'string' ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deactivated' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'activate_plugins' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		deactivate_plugins( (string) $input['file'] );
		return [ 'deactivated' => true ];
	}
}
