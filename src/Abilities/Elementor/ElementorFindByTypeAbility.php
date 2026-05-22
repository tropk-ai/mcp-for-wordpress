<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorFindByTypeAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-find-widgets-by-type'; }
	protected function meta(): array { return [ 'label' => __( 'Find Elementor widgets by type', 'mcp-for-wordpress' ), 'description' => __( 'Returns every widget on a page matching the given widgetType (e.g. heading, button, image-box).', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id', 'widget_type' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'widget_type' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'count' => [ 'type' => 'integer' ], 'widgets' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$all = ElementorPage::load( (int) $input['post_id'] )->widgets();
		$match = array_values( array_filter( $all, static fn( $w ) => ( $w['widgetType'] ?? '' ) === (string) $input['widget_type'] ) );
		return [ 'count' => count( $match ), 'widgets' => $match ];
	}
}
