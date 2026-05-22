<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Plugins;
use Tropk\Mcp\Abilities\AbstractAbility;
final class PluginsListUpdatesAbility extends AbstractAbility {
	public function slug(): string { return 'plugins-list-updates'; }
	protected function meta(): array { return [ 'label' => __( 'List pending plugin updates', 'mcp-for-wordpress' ), 'description' => __( 'Returns plugins with a pending update, including current and new versions.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updates' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'update_plugins' ); }
	public function execute( array $input = [] ): array {
		wp_update_plugins();
		$t = get_site_transient( 'update_plugins' );
		$out = [];
		if ( $t && ! empty( $t->response ) ) {
			foreach ( $t->response as $file => $data ) {
				$out[] = [ 'file' => (string) $file, 'new_version' => (string) ( $data->new_version ?? '' ), 'package' => (string) ( $data->package ?? '' ), 'slug' => (string) ( $data->slug ?? '' ) ];
			}
		}
		return [ 'updates' => $out ];
	}
}
