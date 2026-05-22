<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Plugins;

use Tropk\Mcp\Abilities\AbstractAbility;

final class PluginsDeleteAbility extends AbstractAbility {
	public function slug(): string { return 'plugins-delete'; }
	protected function meta(): array { return [
		'label' => __( 'Delete a plugin', 'mcp-for-wordpress' ),
		'description' => __( 'Removes an inactive plugin from the filesystem.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'file' ],
		'properties'           => [ 'file' => [ 'type' => 'string' ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deleted' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'delete_plugins' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		if ( ! function_exists( 'delete_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$res = delete_plugins( [ (string) $input['file'] ] );
		if ( is_wp_error( $res ) ) {
			throw new \RuntimeException( $res->get_error_message() );
		}
		return [ 'deleted' => true === $res ];
	}
}
