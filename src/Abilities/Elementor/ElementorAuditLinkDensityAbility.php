<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorAuditLinkDensityAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-link-density'; }
	protected function meta(): array { return [ 'label' => __( 'Audit link density', 'mcp-for-wordpress' ), 'description' => __( 'Counts widgets with link settings (button, image, heading-with-link). High density on a short page can signal SEO over-optimisation.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$links = 0; $total = 0;
		foreach ( $page->widgets() as $w ) {
			$total++;
			$node = $page->find_widget( (string) $w["id"] );
			if ( isset( $node["settings"]["link"]["url"] ) && "" !== (string) $node["settings"]["link"]["url"] ) $links++;
			if ( isset( $node["settings"]["button_link"]["url"] ) && "" !== (string) $node["settings"]["button_link"]["url"] ) $links++;
		}
		return [ "result" => [ "total_widgets" => $total, "linked_widgets" => $links, "ratio" => $total ? round( $links / $total, 3 ) : 0 ] ];
	}
}
