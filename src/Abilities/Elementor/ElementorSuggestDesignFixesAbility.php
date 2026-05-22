<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorSuggestDesignFixesAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-suggest-design-fixes'; }
	protected function meta(): array { return [
		'label'       => __( 'Suggest Elementor design fixes', 'mcp-for-wordpress' ),
		'description' => __( 'Returns a prioritized list of suggested fixes derived from common design audit signals.', 'mcp-for-wordpress' ),
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
			'findings'    => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
			'score'       => [ 'type' => 'number' ],
			'suggestions' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
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
		$findings    = [];
		$suggestions = [];
		$widgets = $page->widgets();
		$total   = count( $widgets );
		$counts  = [];
		$h_sizes = [];
		foreach ( $widgets as $w ) {
			$t = (string) ( $w['widgetType'] ?? '' );
			$counts[ $t ] = ( $counts[ $t ] ?? 0 ) + 1;
			if ( 'heading' === $t ) {
				$n = $page->find_widget( (string) $w['id'] );
				$sz = (float) ( $n['settings']['typography_font_size']['size'] ?? 0 );
				$tag = strtolower( (string) ( $n['settings']['header_size'] ?? 'h2' ) );
				if ( $sz > 0 ) { $h_sizes[ $tag ][] = $sz; }
			}
		}
		foreach ( $counts as $type => $n ) {
			if ( $total > 5 && $n / max( 1, $total ) > 0.4 ) {
				$suggestions[] = sprintf( 'Consider varying widget mix — "%s" makes up %d/%d.', $type, $n, $total );
				$findings[] = [ 'level' => 'info', 'message' => 'Widget overuse.', 'widget_type' => $type ];
			}
		}
		if ( ! empty( $h_sizes['h3'] ) && ! empty( $h_sizes['h2'] ) ) {
			$h2 = array_sum( $h_sizes['h2'] ) / count( $h_sizes['h2'] );
			$h3 = array_sum( $h_sizes['h3'] ) / count( $h_sizes['h3'] );
			if ( $h3 > $h2 ) {
				$suggestions[] = 'Reduce H3 font size below H2 to restore visual hierarchy.';
				$findings[] = [ 'level' => 'warn', 'message' => 'Heading hierarchy inverted (H3 >= H2).' ];
			}
		}
		return [ 'findings' => $findings, 'score' => empty( $findings ) ? 1.0 : max( 0.0, 1.0 - 0.15 * count( $findings ) ), 'suggestions' => $suggestions ];
	}
}
