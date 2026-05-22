<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorHasFormWidgetAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-has-form-widget'; }
	protected function meta(): array { return [ 'label' => __( 'Detect form widgets on a page', 'mcp-for-wordpress' ), 'description' => __( 'Returns true if any form or call-to-action with submit-style widget is on the page (form, contact-form-7, wpforms, etc).', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) ;
	}
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$found = [];
		foreach ( $page->widgets() as $w ) {
			$t = (string) ( $w["widgetType"] ?? "" );
			if ( in_array( $t, [ "form", "contact-form-7", "wpforms", "fluentform", "ninja-form", "gravity-form" ], true ) ) {
				$found[] = [ "id" => $w["id"], "type" => $t ];
			}
		}
		return [ "result" => [ "has_form" => ! empty( $found ), "forms" => $found ] ];
	}
}
