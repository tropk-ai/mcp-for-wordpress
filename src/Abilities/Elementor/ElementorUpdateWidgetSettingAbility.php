<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\AtomicProps;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorUpdateWidgetSettingAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-update-widget-setting'; }
	protected function meta(): array { return [
		'label' => __( 'Update an Elementor widget setting', 'mcp-for-wordpress' ),
		'description' => __( 'Sets a single setting key on a widget by ID, then flushes CSS. V4 atomic typed props (e.g. {"$$type":"classes","value":["mb-x"]}) are accepted either as a structured object or as a JSON string and stored as native objects.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'widget_id', 'key', 'value' ],
		'properties'           => [
			'post_id'   => [ 'type' => 'integer', 'minimum' => 1 ],
			'widget_id' => [ 'type' => 'string' ],
			'key'       => [ 'type' => 'string' ],
			'value'     => [ 'description' => 'New setting value (any JSON type).' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		if ( ! ElementorPage::is_elementor_post( $id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $id ) );
		}
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-update-widget-setting' );
		$page = ElementorPage::load( $id );
		// Heal typed atomic props delivered as JSON strings so they persist as
		// native objects (issue #3, bug #2) rather than stringified JSON.
		$value = AtomicProps::normalize_value( $input['value'] );
		$ok   = $page->update_widget_setting( (string) $input['widget_id'], (string) $input['key'], $value );
		if ( $ok ) $page->save();
		return [ 'updated' => $ok, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
