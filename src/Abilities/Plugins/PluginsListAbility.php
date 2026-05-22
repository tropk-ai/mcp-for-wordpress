<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Plugins;

use Tropk\Mcp\Abilities\AbstractAbility;

final class PluginsListAbility extends AbstractAbility {
	public function slug(): string { return 'plugins-list'; }
	protected function meta(): array { return [
		'label' => __( 'List installed plugins', 'mcp-for-wordpress' ),
		'description' => __( 'Returns every installed plugin with name, version, active state and update availability.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'plugins' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'activate_plugins' ); }
	public function execute( array $input = [] ): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all     = get_plugins();
		$active  = (array) get_option( 'active_plugins', [] );
		$updates = get_site_transient( 'update_plugins' );
		$out     = [];
		foreach ( $all as $file => $info ) {
			$out[] = [
				'file'        => (string) $file,
				'name'        => (string) ( $info['Name'] ?? '' ),
				'version'     => (string) ( $info['Version'] ?? '' ),
				'author'      => (string) ( $info['Author'] ?? '' ),
				'description' => (string) ( $info['Description'] ?? '' ),
				'active'      => in_array( $file, $active, true ),
				'has_update'  => isset( $updates->response[ $file ] ),
			];
		}
		return [ 'plugins' => $out ];
	}
}
