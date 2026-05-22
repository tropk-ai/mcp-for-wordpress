<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorEvaluateDesignAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-evaluate-design'; }
	protected function meta(): array { return [
		'label'       => __( 'Evaluate Elementor design', 'mcp-for-wordpress' ),
		'description' => __( 'Aggregates the main Elementor design audit signals (column balance, surface overuse, separator overuse) into one score with grouped findings.', 'mcp-for-wordpress' ),
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
			'findings'   => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
			'score'      => [ 'type' => 'number' ],
			'evaluation' => [ 'type' => 'object' ],
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
		$findings = [];
		$bg_colors = [];
		$dividers  = 0;
		$sections  = 0;
		$walk = static function ( array $node ) use ( &$walk, &$bg_colors, &$dividers ): void {
			$s = $node['settings'] ?? [];
			$c = strtolower( (string) ( $s['background_color'] ?? '' ) );
			if ( '' !== $c ) { $bg_colors[ $c ] = true; }
			$t = (string) ( $node['widgetType'] ?? '' );
			if ( 'divider' === $t || 'spacer' === $t ) { $dividers++; }
			foreach ( (array) ( $node['elements'] ?? [] ) as $k ) { if ( is_array( $k ) ) { $walk( $k ); } }
		};
		foreach ( $page->data() as $top ) {
			if ( ! is_array( $top ) ) { continue; }
			$sections++;
			$walk( $top );
		}
		if ( count( $bg_colors ) > 5 ) {
			$findings[] = [ 'level' => 'warn', 'message' => sprintf( '%d distinct background colors on page.', count( $bg_colors ) ) ];
		}
		if ( $dividers > 10 ) {
			$findings[] = [ 'level' => 'warn', 'message' => sprintf( '%d divider/spacer widgets on page.', $dividers ) ];
		}
		$score = max( 0.0, 1.0 - 0.1 * count( $findings ) );
		return [ 'findings' => $findings, 'score' => $score, 'evaluation' => [ 'sections' => $sections, 'colors' => count( $bg_colors ), 'dividers' => $dividers ] ];
	}
}
