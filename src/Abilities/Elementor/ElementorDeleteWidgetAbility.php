<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorDeleteWidgetAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-delete-widget'; }
	protected function meta(): array { return [
		'label' => __( 'Delete an Elementor widget', 'mcp-for-wordpress' ),
		'description' => __( 'Removes a widget (or container) from the Elementor tree by ID. Snapshots the post first and flushes CSS afterwards.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'widget_id' ],
		'properties'           => [
			'post_id'   => [ 'type' => 'integer', 'minimum' => 1 ],
			'widget_id' => [ 'type' => 'string' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deleted' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id   = (int) $input['post_id'];
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-delete-widget' );
		$page = ElementorPage::load( $id );
		$ok   = $page->delete_widget( (string) $input['widget_id'] );
		if ( $ok ) $page->save();
		return [ 'deleted' => $ok, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
