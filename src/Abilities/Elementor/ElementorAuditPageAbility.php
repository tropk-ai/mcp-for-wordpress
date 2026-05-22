<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorAuditPageAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-audit-page'; }
	protected function meta(): array { return [ 'label' => __( 'Run a complete Elementor page audit', 'mcp-for-wordpress' ), 'description' => __( "Aggregates heading hierarchy + column balance + dominance + atomic widget count in a single call.", 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$widgets = $page->widgets();
		$atomic = 0;
		foreach ( $widgets as $w ) if ( ! empty( $w['atomic'] ) ) $atomic++;
		$h1 = 0;
		foreach ( $widgets as $w ) {
			if ( 'heading' !== ( $w['widgetType'] ?? '' ) ) continue;
			$node = $page->find_widget( (string) $w['id'] );
			if ( strtolower( (string) ( $node['settings']['header_size'] ?? '' ) ) === 'h1' ) $h1++;
		}
		return [ 'result' => [
			'widgets_total'  => count( $widgets ),
			'atomic_widgets' => $atomic,
			'classic_widgets'=> count( $widgets ) - $atomic,
			'h1_count'       => $h1,
			'h1_issues'      => $h1 > 1 ? 'duplicate_h1' : ( 0 === $h1 ? 'missing_h1' : null ),
		] ];
	}
}
