<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorAuditColumnPatternsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-column-patterns'; }
	protected function meta(): array { return [
		'label'       => __( 'Audit Elementor column patterns', 'mcp-for-wordpress' ),
		'description' => __( 'Detects repeated column ratios (e.g. many 50/50 or equal-third rows) so layouts can be intentionally varied.', 'mcp-for-wordpress' ),
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
		$ratios = [];
		$walk = static function ( array $node ) use ( &$walk, &$ratios ): void {
			$dir = strtolower( (string) ( $node['settings']['flex_direction'] ?? '' ) );
			$children = array_values( array_filter( (array) ( $node['elements'] ?? [] ), 'is_array' ) );
			if ( 'row' === $dir && count( $children ) >= 2 ) {
				$widths = [];
				foreach ( $children as $c ) {
					$w = (float) ( $c['settings']['width']['size'] ?? $c['settings']['_inline_size'] ?? 0 );
					$widths[] = $w > 0 ? (string) round( $w ) : 'auto';
				}
				$sig = implode( '|', $widths );
				$ratios[ $sig ] = ( $ratios[ $sig ] ?? [] );
				$ratios[ $sig ][] = (string) ( $node['id'] ?? '' );
			}
			foreach ( $children as $c ) { $walk( $c ); }
		};
		foreach ( ElementorPage::load( $id )->data() as $top ) { if ( is_array( $top ) ) { $walk( $top ); } }
		$findings = [];
		foreach ( $ratios as $sig => $ids ) {
			if ( count( $ids ) > 1 ) {
				$findings[] = [ 'level' => 'info', 'message' => sprintf( 'Repeated column ratio %s used %d times.', $sig, count( $ids ) ), 'ratio_signature' => $sig, 'element_ids' => $ids ];
			}
		}
		return [ 'findings' => $findings, 'score' => empty( $findings ) ? 1.0 : max( 0.0, 1.0 - 0.05 * count( $findings ) ) ];
	}
}
