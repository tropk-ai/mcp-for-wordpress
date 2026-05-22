<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorBatchUpdateAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-batch-update'; }
	protected function meta(): array { return [
		'label' => __( 'Batch update Elementor elements', 'mcp-for-wordpress' ),
		'description' => __( 'Applies multiple settings-merge operations to elements on a single page in one save. Each operation pairs an element_id with a partial settings object.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'operations' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'operations' => [
				'type'  => 'array',
				'minItems' => 1,
				'items' => [
					'type' => 'object',
					'additionalProperties' => false,
					'required'             => [ 'element_id', 'settings' ],
					'properties'           => [
						'element_id' => [ 'type' => 'string', 'minLength' => 1 ],
						'settings'   => [ 'type' => 'object' ],
					],
				],
			],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'updated' => [ 'type' => 'integer' ], 'failed' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
		'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id    = (int) $input['post_id'];
		$ops   = (array) $input['operations'];
		$page  = ElementorPage::load( $id );
		$snap  = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-batch-update' );
		$ok    = 0;
		$fails = [];
		foreach ( $ops as $op ) {
			if ( ! is_array( $op ) ) continue;
			$eid = (string) ( $op['element_id'] ?? '' );
			$settings = is_array( $op['settings'] ?? null ) ? $op['settings'] : [];
			if ( '' === $eid || [] === $settings ) {
				$fails[] = [ 'element_id' => $eid, 'reason' => 'missing element_id or settings' ];
				continue;
			}
			if ( null === $page->find_widget( $eid ) ) {
				$fails[] = [ 'element_id' => $eid, 'reason' => 'element not found' ];
				continue;
			}
			$applied = 0;
			foreach ( $settings as $k => $v ) {
				if ( $page->update_widget_setting( $eid, (string) $k, $v ) ) $applied++;
			}
			if ( $applied > 0 ) $ok++;
			else $fails[] = [ 'element_id' => $eid, 'reason' => 'update failed' ];
		}
		$page->save();
		return [ 'updated' => $ok, 'failed' => $fails, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
