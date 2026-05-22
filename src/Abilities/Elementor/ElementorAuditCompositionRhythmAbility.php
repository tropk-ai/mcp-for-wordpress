<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorAuditCompositionRhythmAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-composition-rhythm'; }
	protected function meta(): array { return [
		'label'       => __( 'Audit Elementor composition rhythm', 'mcp-for-wordpress' ),
		'description' => __( 'Looks at how top-level sections alternate (single vs multi-column, sizes) and flags monotonous rhythm.', 'mcp-for-wordpress' ),
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
		$beats = [];
		foreach ( ElementorPage::load( $id )->data() as $section ) {
			if ( ! is_array( $section ) ) { continue; }
			$kids = array_values( array_filter( (array) ( $section['elements'] ?? [] ), 'is_array' ) );
			$beats[] = count( $kids ) <= 1 ? 'single' : 'multi';
		}
		$findings = [];
		$runs = 0;
		for ( $i = 1; $i < count( $beats ); $i++ ) {
			if ( $beats[ $i ] === $beats[ $i - 1 ] ) { $runs++; }
		}
		if ( $runs >= 4 ) {
			$findings[] = [ 'level' => 'info', 'message' => sprintf( 'Composition is monotonous: %d consecutive sections share the same rhythm.', $runs ), 'beat_sequence' => $beats ];
		}
		return [ 'findings' => $findings, 'score' => 1.0 - min( 1.0, $runs * 0.1 ) ];
	}
}
