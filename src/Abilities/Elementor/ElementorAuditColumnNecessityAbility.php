<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorAuditColumnNecessityAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-column-necessity'; }
	protected function meta(): array { return [
		'label'       => __( 'Audit Elementor column necessity', 'mcp-for-wordpress' ),
		'description' => __( 'Flags multi-column rows that could read more clearly as a single lane (e.g. only one column has substantive content).', 'mcp-for-wordpress' ),
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
		$count    = static function ( array $node ) use ( &$count ): int {
			if ( 'widget' === ( $node['elType'] ?? '' ) ) { return 1; }
			$n = 0;
			foreach ( (array) ( $node['elements'] ?? [] ) as $c ) { if ( is_array( $c ) ) { $n += $count( $c ); } }
			return $n;
		};
		$walk = static function ( array $node ) use ( &$walk, &$findings, $count ): void {
			$dir = strtolower( (string) ( $node['settings']['flex_direction'] ?? '' ) );
			$children = array_values( array_filter( (array) ( $node['elements'] ?? [] ), 'is_array' ) );
			if ( 'row' === $dir && count( $children ) >= 2 ) {
				$loads = array_map( $count, $children );
				$nonEmpty = count( array_filter( $loads, static fn( $n ) => $n > 0 ) );
				if ( $nonEmpty <= 1 ) {
					$findings[] = [ 'level' => 'warn', 'message' => 'Row splits into columns but only one column carries content.', 'element_id' => (string) ( $node['id'] ?? '' ), 'child_counts' => $loads ];
				}
			}
			foreach ( $children as $c ) { $walk( $c ); }
		};
		foreach ( ElementorPage::load( $id )->data() as $top ) { if ( is_array( $top ) ) { $walk( $top ); } }
		return [ 'findings' => $findings, 'score' => empty( $findings ) ? 1.0 : max( 0.0, 1.0 - 0.1 * count( $findings ) ) ];
	}
}
