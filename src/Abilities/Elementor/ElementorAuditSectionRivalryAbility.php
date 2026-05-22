<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorAuditSectionRivalryAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-section-rivalry'; }
	protected function meta(): array { return [
		'label'       => __( 'Audit section rivalry', 'mcp-for-wordpress' ),
		'description' => __( 'Flags adjacent sections that fight for attention (e.g. heavy backgrounds back-to-back, two heroes next to each other).', 'mcp-for-wordpress' ),
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
		$sigs = [];
		foreach ( ElementorPage::load( $id )->data() as $section ) {
			if ( ! is_array( $section ) ) { continue; }
			$s = $section['settings'] ?? [];
			$bg = (string) ( $s['background_background'] ?? '' );
			$heavy = ( 'classic' === $bg || 'gradient' === $bg || ! empty( $s['background_image']['url'] ) );
			$sigs[] = [ 'id' => (string) ( $section['id'] ?? '' ), 'heavy' => $heavy ];
		}
		$findings = [];
		for ( $i = 1; $i < count( $sigs ); $i++ ) {
			if ( $sigs[ $i ]['heavy'] && $sigs[ $i - 1 ]['heavy'] ) {
				$findings[] = [ 'level' => 'warn', 'message' => 'Two adjacent sections both have heavy backgrounds — they compete for attention.', 'element_ids' => [ $sigs[ $i - 1 ]['id'], $sigs[ $i ]['id'] ] ];
			}
		}
		return [ 'findings' => $findings, 'score' => empty( $findings ) ? 1.0 : max( 0.0, 1.0 - 0.2 * count( $findings ) ) ];
	}
}
