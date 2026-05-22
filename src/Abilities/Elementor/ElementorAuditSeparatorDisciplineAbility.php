<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorAuditSeparatorDisciplineAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-separator-discipline'; }
	protected function meta(): array { return [
		'label'       => __( 'Audit separator / divider discipline', 'mcp-for-wordpress' ),
		'description' => __( 'Counts divider and spacer widgets per section and flags overuse (>5 per section) and stacked dividers.', 'mcp-for-wordpress' ),
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
		$count_dividers = static function ( array $node, &$count ) use ( &$count_dividers ): void {
			$t = (string) ( $node['widgetType'] ?? '' );
			if ( 'divider' === $t || 'spacer' === $t ) { $count++; }
			foreach ( (array) ( $node['elements'] ?? [] ) as $c ) { if ( is_array( $c ) ) { $count_dividers( $c, $count ); } }
		};
		foreach ( ElementorPage::load( $id )->data() as $section ) {
			if ( ! is_array( $section ) ) { continue; }
			$n = 0;
			$count_dividers( $section, $n );
			if ( $n > 5 ) {
				$findings[] = [ 'level' => 'warn', 'message' => sprintf( 'Section uses %d dividers/spacers.', $n ), 'element_id' => (string) ( $section['id'] ?? '' ), 'count' => $n ];
			}
		}
		return [ 'findings' => $findings, 'score' => empty( $findings ) ? 1.0 : max( 0.0, 1.0 - 0.1 * count( $findings ) ) ];
	}
}
