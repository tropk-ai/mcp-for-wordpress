<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorAuditNativeWidgetOpportunitiesAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-native-widget-opportunities'; }
	protected function meta(): array { return [
		'label'       => __( 'Audit native widget opportunities', 'mcp-for-wordpress' ),
		'description' => __( 'Flags hand-rolled component patterns (icon + heading + text in a container) that could use a native icon-box / image-box widget.', 'mcp-for-wordpress' ),
		'readonly'    => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'findings' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
			'score'    => [ 'type' => 'number' ],
		],
	]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) );
	}
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		if ( ! ElementorPage::is_elementor_post( $id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $id ) );
		}
		$findings = [];
		$walk = static function ( array $node ) use ( &$walk, &$findings ): void {
			$kids = array_values( array_filter( (array) ( $node['elements'] ?? [] ), 'is_array' ) );
			$types = array_map( static fn( $k ) => (string) ( $k['widgetType'] ?? $k['elType'] ?? '' ), $kids );
			$has = static fn( string $t ): bool => in_array( $t, $types, true );
			if ( ( $has( 'icon' ) || $has( 'image' ) ) && $has( 'heading' ) && ( $has( 'text-editor' ) || $has( 'text-path' ) ) ) {
				$findings[] = [ 'level' => 'info', 'message' => 'Container reproduces the structure of icon-box / image-box.', 'element_id' => (string) ( $node['id'] ?? '' ), 'children' => $types ];
			}
			foreach ( $kids as $c ) { $walk( $c ); }
		};
		foreach ( ElementorPage::load( $id )->data() as $top ) { if ( is_array( $top ) ) { $walk( $top ); } }
		return [ 'findings' => $findings, 'score' => empty( $findings ) ? 1.0 : max( 0.0, 1.0 - 0.05 * count( $findings ) ) ];
	}
}
