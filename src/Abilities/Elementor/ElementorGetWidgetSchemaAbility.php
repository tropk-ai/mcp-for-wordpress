<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorGetWidgetSchemaAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-widget-schema'; }
	protected function meta(): array { return [ 'label' => __( 'Get widget JSON schema (settings)', 'mcp-for-wordpress' ), 'description' => __( 'Returns the widget node settings + a list of which top-level setting keys are populated.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$node = $page->find_widget( "all" );
		// Implementation: get raw settings; this convenience just enumerates settings on first widget if any.
		$first = $page->widgets()[ 0 ] ?? null;
		if ( ! $first ) return [ "result" => [ "widgets" => 0 ] ];
		$n = $page->find_widget( (string) $first["id"] );
		$keys = is_array( $n["settings"] ?? null ) ? array_keys( $n["settings"] ) : [];
		return [ "result" => [ "first_widget_id" => $first["id"], "widget_type" => $first["widgetType"], "settings_keys" => $keys ] ];
	}
}
