<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorAuditSurfaceOveruseAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-surface-overuse'; }
	protected function meta(): array { return [
		'label'       => __( 'Audit surface overuse', 'mcp-for-wordpress' ),
		'description' => __( 'Flags pages with too many distinct background colors / images stacking visual weight.', 'mcp-for-wordpress' ),
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
		$colors = [];
		$walk = static function ( array $node ) use ( &$walk, &$colors ): void {
			$s = $node['settings'] ?? [];
			$c = strtolower( trim( (string) ( $s['background_color'] ?? '' ) ) );
			if ( '' !== $c ) { $colors[ $c ] = ( $colors[ $c ] ?? 0 ) + 1; }
			foreach ( (array) ( $node['elements'] ?? [] ) as $k ) { if ( is_array( $k ) ) { $walk( $k ); } }
		};
		foreach ( ElementorPage::load( $id )->data() as $top ) { if ( is_array( $top ) ) { $walk( $top ); } }
		$findings = [];
		if ( count( $colors ) > 5 ) {
			$findings[] = [ 'level' => 'warn', 'message' => sprintf( 'Page uses %d distinct background colors.', count( $colors ) ), 'colors' => $colors ];
		}
		return [ 'findings' => $findings, 'score' => empty( $findings ) ? 1.0 : max( 0.0, 1.0 - 0.1 * ( count( $colors ) - 5 ) ) ];
	}
}
