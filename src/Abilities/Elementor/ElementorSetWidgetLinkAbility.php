<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorSetWidgetLinkAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-set-widget-link'; }
	protected function meta(): array { return [ 'label' => __( 'Set link on an Elementor widget', 'mcp-for-wordpress' ), 'description' => __( 'Updates the link URL on a widget by ID (button, image, heading-with-link).', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required' => [ 'post_id', 'widget_id', 'url' ],
		'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'widget_id' => [ 'type' => 'string' ], 'url' => [ 'type' => 'string' ], 'new_tab' => [ 'type' => 'boolean' ], 'nofollow' => [ 'type' => 'boolean' ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id   = (int) $input['post_id'];
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-set-widget-link' );
		$page = ElementorPage::load( $id );
		$ok = $page->update_widget_setting( (string) $input['widget_id'], 'link', [ 'url' => (string) $input['url'], 'is_external' => ! empty( $input['new_tab'] ) ? 'on' : '', 'nofollow' => ! empty( $input['nofollow'] ) ? 'on' : '' ] );
		if ( $ok ) $page->save();
		return [ 'updated' => $ok, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
