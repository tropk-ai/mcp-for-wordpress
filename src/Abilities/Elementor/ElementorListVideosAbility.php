<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorListVideosAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-videos'; }
	protected function meta(): array { return [ 'label' => __( 'List Elementor video widgets', 'mcp-for-wordpress' ), 'description' => __( 'Returns every video widget with source + URL.', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) ;
	}
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$out = [];
		foreach ( $page->widgets() as $w ) {
			$t = (string) ( $w["widgetType"] ?? "" );
			if ( ! in_array( $t, [ "video", "youtube", "vimeo" ], true ) ) continue;
			$n = $page->find_widget( (string) $w["id"] );
			$out[] = [ "id" => $w["id"], "type" => $t, "url" => (string) ( $n["settings"]["youtube_url"] ?? $n["settings"]["vimeo_url"] ?? $n["settings"]["url"] ?? "" ) ];
		}
		return [ "result" => [ "videos" => $out ] ];
	}
}
