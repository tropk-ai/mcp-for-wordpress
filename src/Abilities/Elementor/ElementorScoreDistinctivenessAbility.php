<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorScoreDistinctivenessAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-score-distinctiveness'; }
	protected function meta(): array { return [
		'label'       => __( 'Score page distinctiveness', 'mcp-for-wordpress' ),
		'description' => __( 'Computes a 0..1 distinctiveness score based on variation in widget types, column ratios and background usage. Higher = more visually varied.', 'mcp-for-wordpress' ),
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
			'metrics'  => [ 'type' => 'object' ],
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
		$page = ElementorPage::load( $id );
		$widgets  = $page->widgets();
		$types    = array_unique( array_map( static fn( $w ) => (string) ( $w['widgetType'] ?? '' ), $widgets ) );
		$colors   = [];
		$ratios   = [];
		$walk = static function ( array $node ) use ( &$walk, &$colors, &$ratios ): void {
			$s = $node['settings'] ?? [];
			$c = strtolower( (string) ( $s['background_color'] ?? '' ) );
			if ( '' !== $c ) { $colors[ $c ] = true; }
			$kids = array_values( array_filter( (array) ( $node['elements'] ?? [] ), 'is_array' ) );
			$dir  = strtolower( (string) ( $s['flex_direction'] ?? '' ) );
			if ( 'row' === $dir && count( $kids ) >= 2 ) {
				$ws = array_map( static fn( $c ) => (float) ( $c['settings']['width']['size'] ?? $c['settings']['_inline_size'] ?? 0 ), $kids );
				$ratios[ implode( '|', $ws ) ] = true;
			}
			foreach ( $kids as $k ) { $walk( $k ); }
		};
		foreach ( $page->data() as $t ) { if ( is_array( $t ) ) { $walk( $t ); } }
		$total = max( 1, count( $widgets ) );
		$type_score  = min( 1.0, count( $types ) / 8 );
		$ratio_score = min( 1.0, count( $ratios ) / 4 );
		$color_score = min( 1.0, count( $colors ) / 3 );
		$score = round( 0.4 * $type_score + 0.3 * $ratio_score + 0.3 * $color_score, 3 );
		return [
			'findings' => [],
			'score'    => $score,
			'metrics'  => [ 'widget_types' => count( $types ), 'distinct_ratios' => count( $ratios ), 'distinct_bg_colors' => count( $colors ), 'total_widgets' => $total ],
		];
	}
}
