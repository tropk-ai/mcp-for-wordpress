<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorAuditEmphasisDriftAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-emphasis-drift'; }
	protected function meta(): array { return [
		'label'       => __( 'Audit Elementor emphasis drift', 'mcp-for-wordpress' ),
		'description' => __( 'Flags hierarchy inversions — e.g. H3 visually larger than H2, or many widgets sharing the same heading level.', 'mcp-for-wordpress' ),
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
		$page  = ElementorPage::load( $id );
		$sizes = [];
		foreach ( $page->widgets() as $w ) {
			if ( 'heading' !== ( $w['widgetType'] ?? '' ) ) { continue; }
			$node  = $page->find_widget( (string) $w['id'] );
			$tag   = strtolower( (string) ( $node['settings']['header_size'] ?? 'h2' ) );
			$size  = (float) ( $node['settings']['typography_font_size']['size'] ?? 0 );
			$sizes[ $tag ][] = $size;
		}
		$findings = [];
		$avg = [];
		foreach ( $sizes as $tag => $vals ) {
			$nonzero = array_filter( $vals, static fn( $v ) => $v > 0 );
			$avg[ $tag ] = $nonzero ? array_sum( $nonzero ) / count( $nonzero ) : 0.0;
		}
		foreach ( [ [ 'h1', 'h2' ], [ 'h2', 'h3' ], [ 'h3', 'h4' ] ] as [ $hi, $lo ] ) {
			if ( ! empty( $avg[ $hi ] ) && ! empty( $avg[ $lo ] ) && $avg[ $lo ] > $avg[ $hi ] ) {
				$findings[] = [ 'level' => 'warn', 'message' => sprintf( 'Average %s size (%.1f) exceeds %s (%.1f) — hierarchy inverted.', $lo, $avg[ $lo ], $hi, $avg[ $hi ] ) ];
			}
		}
		return [ 'findings' => $findings, 'score' => empty( $findings ) ? 1.0 : max( 0.0, 1.0 - 0.3 * count( $findings ) ) ];
	}
}
