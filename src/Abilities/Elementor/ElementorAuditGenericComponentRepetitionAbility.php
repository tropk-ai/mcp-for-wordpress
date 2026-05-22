<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorAuditGenericComponentRepetitionAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-generic-component-repetition'; }
	protected function meta(): array { return [
		'label'       => __( 'Audit generic component repetition', 'mcp-for-wordpress' ),
		'description' => __( 'Counts how often each widget type appears; flags widgets that account for an outsized share of the page.', 'mcp-for-wordpress' ),
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
		$counts = [];
		foreach ( ElementorPage::load( $id )->widgets() as $w ) {
			$t = (string) ( $w['widgetType'] ?? '' );
			if ( '' === $t ) { continue; }
			$counts[ $t ] = ( $counts[ $t ] ?? 0 ) + 1;
		}
		$total = array_sum( $counts );
		$findings = [];
		foreach ( $counts as $type => $n ) {
			if ( $total > 5 && $n >= 4 && $n / max( 1, $total ) > 0.4 ) {
				$findings[] = [ 'level' => 'info', 'message' => sprintf( 'Widget "%s" makes up %d of %d widgets (%.0f%%).', $type, $n, $total, 100 * $n / $total ), 'widget_type' => $type, 'count' => $n ];
			}
		}
		return [ 'findings' => $findings, 'score' => empty( $findings ) ? 1.0 : max( 0.0, 1.0 - 0.15 * count( $findings ) ) ];
	}
}
