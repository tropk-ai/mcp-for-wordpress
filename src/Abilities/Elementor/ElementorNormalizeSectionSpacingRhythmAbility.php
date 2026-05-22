<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorNormalizeSectionSpacingRhythmAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-normalize-section-spacing-rhythm'; }
	protected function meta(): array { return [
		'label'       => __( 'Normalize section spacing rhythm', 'mcp-for-wordpress' ),
		'description' => __( 'Sets every top-level section to a consistent vertical padding so the page has a single rhythm.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [
			'post_id'      => [ 'type' => 'integer', 'minimum' => 1 ],
			'top_padding'  => [ 'type' => 'integer', 'default' => 60 ],
			'bot_padding'  => [ 'type' => 'integer', 'default' => 60 ],
			'dry_run'      => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'updated'       => [ 'type' => 'boolean' ],
			'changed_count' => [ 'type' => 'integer' ],
			'snapshot_id'   => [ 'type' => [ 'string', 'null' ] ],
		],
	]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		if ( ! ElementorPage::is_elementor_post( $id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $id ) );
		}
		$dry = (bool) ( $input['dry_run'] ?? false );
		$top = (int) ( $input['top_padding'] ?? 60 );
		$bot = (int) ( $input['bot_padding'] ?? 60 );
		$page = ElementorPage::load( $id );
		$changed = 0;
		foreach ( $page->data() as $section ) {
			if ( ! is_array( $section ) ) { continue; }
			$sid = (string) ( $section['id'] ?? '' );
			$page->update_widget_setting( $sid, 'padding', [ 'unit' => 'px', 'top' => $top, 'right' => 0, 'bottom' => $bot, 'left' => 0, 'isLinked' => false ] );
			$changed++;
		}
		$snap = null;
		if ( ! $dry && $changed > 0 ) {
			$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-normalize-section-spacing-rhythm' );
			$page->save();
		}
		return [ 'updated' => ! $dry && $changed > 0, 'changed_count' => $changed, 'snapshot_id' => $snap['snapshot_id'] ?? null ];
	}
}
