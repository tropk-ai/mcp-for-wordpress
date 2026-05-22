<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorListResponsiveSettingsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-responsive-settings'; }
	protected function meta(): array { return [ 'label' => __( 'List per-device overrides', 'mcp-for-wordpress' ), 'description' => __( 'Returns widgets that declare _tablet/_mobile specific values (typography_font_size_tablet etc).', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false ]; }
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
			$mobile = []; $tablet = [];
			foreach ( (array) ( $n["settings"] ?? [] ) as $k => $v ) {
				if ( ! is_string( $k ) ) continue;
				if ( str_ends_with( $k, "_mobile" ) ) $mobile[] = $k;
				if ( str_ends_with( $k, "_tablet" ) ) $tablet[] = $k;
			}
			if ( $mobile || $tablet ) $out[] = [ "id" => $w["id"], "mobile_keys" => $mobile, "tablet_keys" => $tablet ];
		}
		return [ "result" => [ "widgets" => $out ] ];
	}
}
