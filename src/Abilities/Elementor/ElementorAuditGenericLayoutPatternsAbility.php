<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorAuditGenericLayoutPatternsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-generic-layout-patterns'; }
	protected function meta(): array { return [
		'label'       => __( 'Audit generic layout patterns', 'mcp-for-wordpress' ),
		'description' => __( 'Flags sections that look like the default "hero + features + grid" template without distinguishing styling.', 'mcp-for-wordpress' ),
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
		foreach ( ElementorPage::load( $id )->data() as $section ) {
			if ( ! is_array( $section ) ) { continue; }
			$s   = $section['settings'] ?? [];
			$bg  = (string) ( $s['background_background'] ?? '' );
			$pad = $s['padding']['top'] ?? null;
			if ( '' === $bg && null === $pad ) {
				$findings[] = [ 'level' => 'info', 'message' => 'Section has neither background nor padding — pure default layout.', 'element_id' => (string) ( $section['id'] ?? '' ) ];
			}
		}
		return [ 'findings' => $findings, 'score' => empty( $findings ) ? 1.0 : max( 0.0, 1.0 - 0.1 * count( $findings ) ) ];
	}
}
