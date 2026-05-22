<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorReplaceImageAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-replace-image'; }
	protected function meta(): array { return [ 'label' => __( 'Replace an image in an Elementor page', 'mcp-for-wordpress' ), 'description' => __( "Updates the image URL on a specific widget by ID. Targets standard image / image_box / background_image fields.", 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id', 'widget_id', 'new_url' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'widget_id' => [ 'type' => 'string' ], 'new_url' => [ 'type' => 'string', 'format' => 'uri' ], 'new_id' => [ 'type' => 'integer', 'minimum' => 0 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-replace-image' );
		$page = ElementorPage::load( $id );
		$new_url = (string) $input['new_url'];
		$new_id  = (int) ( $input['new_id'] ?? 0 );
		$any = false;
		foreach ( [ 'image', 'image_box', 'background_image', 'background_overlay_image' ] as $key ) {
			if ( $page->update_widget_setting( (string) $input['widget_id'], $key, [ 'url' => $new_url, 'id' => $new_id ] ) ) $any = true;
		}
		if ( $any ) $page->save();
		return [ 'updated' => $any, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
