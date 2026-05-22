<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorExtractDesignTokensAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-extract-design-tokens'; }
	protected function meta(): array { return [
		'label'       => __( 'Extract design tokens', 'mcp-for-wordpress' ),
		'description' => __( 'Aggregates colors, font families, font sizes, and spacing values used on the page into a single token report.', 'mcp-for-wordpress' ),
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
			'tokens'   => [ 'type' => 'object' ],
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
		$colors = [];
		$fonts  = [];
		$sizes  = [];
		$pads   = [];
		$collect = static function ( array $node ) use ( &$collect, &$colors, &$fonts, &$sizes, &$pads ): void {
			$s = (array) ( $node['settings'] ?? [] );
			foreach ( $s as $k => $v ) {
				if ( is_string( $v ) && preg_match( '/^#[0-9a-fA-F]{3,8}$/', $v ) ) { $colors[ strtolower( $v ) ] = ( $colors[ strtolower( $v ) ] ?? 0 ) + 1; }
				if ( 'typography_font_family' === $k && is_string( $v ) && '' !== $v ) { $fonts[ $v ] = ( $fonts[ $v ] ?? 0 ) + 1; }
				if ( 'typography_font_size' === $k && is_array( $v ) && isset( $v['size'] ) ) {
					$key = $v['size'] . ( $v['unit'] ?? 'px' );
					$sizes[ $key ] = ( $sizes[ $key ] ?? 0 ) + 1;
				}
				if ( in_array( $k, [ 'padding', 'margin' ], true ) && is_array( $v ) ) {
					$key = ( $v['top'] ?? '' ) . ',' . ( $v['right'] ?? '' ) . ',' . ( $v['bottom'] ?? '' ) . ',' . ( $v['left'] ?? '' );
					$pads[ $key ] = ( $pads[ $key ] ?? 0 ) + 1;
				}
			}
			foreach ( (array) ( $node['elements'] ?? [] ) as $c ) { if ( is_array( $c ) ) { $collect( $c ); } }
		};
		foreach ( ElementorPage::load( $id )->data() as $t ) { if ( is_array( $t ) ) { $collect( $t ); } }
		return [
			'findings' => [],
			'score'    => 1.0,
			'tokens'   => [ 'colors' => $colors, 'fonts' => $fonts, 'font_sizes' => $sizes, 'spacing' => $pads ],
		];
	}
}
