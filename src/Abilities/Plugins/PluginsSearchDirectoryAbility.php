<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Plugins;
use Tropk\Mcp\Abilities\AbstractAbility;
final class PluginsSearchDirectoryAbility extends AbstractAbility {
	public function slug(): string { return 'plugins-search-directory'; }
	protected function meta(): array { return [ 'label' => __( 'Search wordpress.org plugins', 'mcp-for-wordpress' ), 'description' => __( 'Queries the wordpress.org plugin directory by keyword. Returns top matches with name, slug, rating, download count.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'query' ], 'properties' => [ 'query' => [ 'type' => 'string', 'minLength' => 2 ], 'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'plugins' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'install_plugins' ); }
	public function execute( array $input = [] ): array {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		$res = plugins_api( 'query_plugins', [
			'search'   => (string) $input['query'],
			'per_page' => (int) ( $input['per_page'] ?? 10 ),
			'fields'   => [ 'short_description' => true, 'rating' => true, 'downloaded' => true ],
		] );
		if ( is_wp_error( $res ) ) throw new \RuntimeException( $res->get_error_message() );
		$out = [];
		foreach ( (array) ( $res->plugins ?? [] ) as $p ) {
			$out[] = [
				'name' => (string) ( $p->name ?? '' ),
				'slug' => (string) ( $p->slug ?? '' ),
				'version' => (string) ( $p->version ?? '' ),
				'rating' => (float) ( $p->rating ?? 0 ),
				'downloaded' => (int) ( $p->downloaded ?? 0 ),
				'short_description' => (string) ( $p->short_description ?? '' ),
			];
		}
		return [ 'plugins' => $out ];
	}
}
