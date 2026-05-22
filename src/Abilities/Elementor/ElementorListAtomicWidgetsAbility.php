<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorListAtomicWidgetsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-atomic-widgets'; }
	protected function meta(): array { return [ 'label' => __( 'List atomic (V4) widgets on a page', 'mcp-for-wordpress' ), 'description' => __( 'Returns the IDs and widgetType of every Elementor V4 atomic widget. Settings are opaque and not exposed.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'atomic_widgets' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$found = array_values( array_filter( ElementorPage::load( (int) $input['post_id'] )->widgets(), static fn( $w ) => ! empty( $w['atomic'] ) ) );
		return [ 'atomic_widgets' => array_map( static fn( $w ) => [ 'id' => $w['id'], 'widgetType' => $w['widgetType'] ], $found ) ];
	}
}
