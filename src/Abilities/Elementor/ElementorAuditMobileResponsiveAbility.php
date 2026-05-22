<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorAuditMobileResponsiveAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-mobile-responsive'; }
	protected function meta(): array { return [ 'label' => __( 'Audit hidden-on-mobile widgets', 'mcp-for-wordpress' ), 'description' => __( 'Returns widgets that are hidden on mobile (hide_mobile=hidden-phone).', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) ;
	}
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$hidden = [];
		foreach ( $page->widgets() as $w ) {
			$n = $page->find_widget( (string) $w["id"] );
			$cls = (string) ( $n["settings"]["_css_classes"] ?? "" );
			$flags = [];
			foreach ( [ "hidden_phone", "hidden_tablet", "hidden_desktop" ] as $k ) {
				if ( ! empty( $n["settings"][ $k ] ) ) $flags[] = $k;
			}
			if ( $flags || false !== strpos( $cls, "elementor-hidden-" ) ) $hidden[] = [ "id" => $w["id"], "flags" => $flags ];
		}
		return [ "result" => [ "hidden_widgets" => $hidden ] ];
	}
}
