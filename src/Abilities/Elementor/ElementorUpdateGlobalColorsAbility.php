<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorUpdateGlobalColorsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-update-global-colors'; }
	protected function meta(): array { return [
		'label'       => __( 'Update Elementor global colors', 'mcp-for-wordpress' ),
		'description' => __( "Updates the active kit's custom_colors palette. Provide an array of {_id,title,color} entries; existing IDs are replaced, new ones are appended.", 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'colors' ],
		'properties'           => [
			'colors' => [
				'type'  => 'array',
				'items' => [
					'type'       => 'object',
					'required'   => [ '_id', 'title', 'color' ],
					'properties' => [
						'_id'   => [ 'type' => 'string' ],
						'title' => [ 'type' => 'string' ],
						'color' => [ 'type' => 'string' ],
					],
				],
			],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'kit_id' => [ 'type' => 'integer' ], 'custom_colors' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$colors = (array) ( $input['colors'] ?? [] );
		if ( empty( $colors ) ) {
			throw new \RuntimeException( 'colors is required.' );
		}
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit_id ) {
			throw new \RuntimeException( 'Active Elementor kit not found.' );
		}
		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		$settings = is_array( $settings ) ? $settings : [];
		$existing = (array) ( $settings['custom_colors'] ?? [] );
		$by_id    = [];
		foreach ( $existing as $i => $row ) {
			if ( isset( $row['_id'] ) ) $by_id[ (string) $row['_id'] ] = $i;
		}
		foreach ( $colors as $c ) {
			$id = sanitize_text_field( (string) ( $c['_id'] ?? '' ) );
			if ( '' === $id ) continue;
			$entry = [
				'_id'   => $id,
				'title' => sanitize_text_field( (string) ( $c['title'] ?? '' ) ),
				'color' => (string) ( sanitize_hex_color( (string) ( $c['color'] ?? '' ) ) ?? '' ),
			];
			if ( isset( $by_id[ $id ] ) ) {
				$existing[ $by_id[ $id ] ] = $entry;
			} else {
				$existing[] = $entry;
			}
		}
		$settings['custom_colors'] = array_values( $existing );
		update_post_meta( $kit_id, '_elementor_page_settings', $settings );
		return [ 'kit_id' => $kit_id, 'custom_colors' => $settings['custom_colors'] ];
	}
}
