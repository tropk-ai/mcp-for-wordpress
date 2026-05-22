<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorListLinksAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-links'; }
	protected function meta(): array { return [ 'label' => __( 'List every external link on a page', 'mcp-for-wordpress' ), 'description' => __( 'Returns every link.url in widgets and flags external/nofollow ones.', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) ;
	}
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$out = [];
		foreach ( $page->widgets() as $w ) {
			$n = $page->find_widget( (string) $w["id"] );
			foreach ( [ "link", "url", "button_link" ] as $key ) {
				$url = is_array( $n["settings"][ $key ] ?? null ) ? (string) ( $n["settings"][ $key ]["url"] ?? "" ) : "";
				if ( "" === $url ) continue;
				$host = (string) wp_parse_url( $url, PHP_URL_HOST );
				$out[] = [ "widget_id" => $w["id"], "url" => $url, "external" => $host && $host !== $site_host ];
			}
		}
		return [ "result" => [ "links" => $out, "count" => count( $out ) ] ];
	}
}
