<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorCheckBrokenLinksAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-check-broken-links'; }
	protected function meta(): array { return [ 'label' => __( 'Check links for HTTP errors', 'mcp-for-wordpress' ), 'description' => __( 'Probes every link URL with a HEAD request and reports the status code.', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) ;
	}
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$out = [];
		foreach ( $page->widgets() as $w ) {
			$n = $page->find_widget( (string) $w["id"] );
			$url = is_array( $n["settings"]["link"] ?? null ) ? (string) ( $n["settings"]["link"]["url"] ?? "" ) : "";
			if ( "" === $url || str_starts_with( $url, "#" ) || str_starts_with( $url, "mailto:" ) ) continue;
			$resp = wp_remote_head( $url, [ "timeout" => 5 ] );
			$code = is_wp_error( $resp ) ? 0 : (int) wp_remote_retrieve_response_code( $resp );
			if ( $code >= 400 || 0 === $code ) $out[] = [ "widget_id" => $w["id"], "url" => $url, "status" => $code ];
		}
		return [ "result" => [ "broken_links" => $out ] ];
	}
}
