<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorAuditLayoutMechanismFitAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-layout-mechanism-fit'; }
	protected function meta(): array { return [
		'label'       => __( 'Audit layout mechanism fit', 'mcp-for-wordpress' ),
		'description' => __( 'Suggests Grid for symmetric equal-column rows that currently use Flexbox, following Elementor official guidance.', 'mcp-for-wordpress' ),
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
			$s = $node['settings'] ?? [];
			$dir = strtolower( (string) ( $s['flex_direction'] ?? '' ) );
			$layout = (string) ( $s['container_type'] ?? $s['layout'] ?? 'flex' );
			$kids = array_values( array_filter( (array) ( $node['elements'] ?? [] ), 'is_array' ) );
			if ( 'row' === $dir && 'grid' !== $layout && count( $kids ) >= 2 ) {
				$widths = [];
				foreach ( $kids as $c ) {
					$widths[] = (float) ( $c['settings']['width']['size'] ?? $c['settings']['_inline_size'] ?? 0 );
				}
				if ( count( array_unique( $widths ) ) === 1 && $widths[0] > 0 ) {
					$findings[] = [ 'level' => 'info', 'message' => 'Equal-width flex row could be Grid for symmetric layout.', 'element_id' => (string) ( $node['id'] ?? '' ), 'columns' => count( $kids ) ];
				}
			}
			foreach ( $kids as $c ) { $walk( $c ); }
		};
		foreach ( ElementorPage::load( $id )->data() as $top ) { if ( is_array( $top ) ) { $walk( $top ); } }
		return [ 'findings' => $findings, 'score' => empty( $findings ) ? 1.0 : max( 0.0, 1.0 - 0.1 * count( $findings ) ) ];
	}
}
