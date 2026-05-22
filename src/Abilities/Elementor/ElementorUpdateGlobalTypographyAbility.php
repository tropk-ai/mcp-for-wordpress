<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorUpdateGlobalTypographyAbility extends AbstractAbility {
	private const ALLOWED = [
		'_id', 'title', 'typography_typography', 'typography_font_family', 'typography_font_size',
		'typography_font_weight', 'typography_text_transform', 'typography_font_style',
		'typography_text_decoration', 'typography_line_height', 'typography_letter_spacing', 'typography_word_spacing',
	];

	public function slug(): string { return 'elementor-update-global-typography'; }
	protected function meta(): array { return [
		'label'       => __( 'Update Elementor global typography', 'mcp-for-wordpress' ),
		'description' => __( "Updates the active kit's custom_typography presets. Existing IDs are merged, new ones are appended. Forces typography_typography=custom on each entry.", 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'typography' ],
		'properties'           => [
			'typography' => [
				'type'  => 'array',
				'items' => [ 'type' => 'object', 'required' => [ '_id', 'title' ], 'properties' => [ '_id' => [ 'type' => 'string' ], 'title' => [ 'type' => 'string' ] ] ],
			],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'kit_id' => [ 'type' => 'integer' ], 'custom_typography' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$typo = (array) ( $input['typography'] ?? [] );
		if ( empty( $typo ) ) {
			throw new \RuntimeException( 'typography is required.' );
		}
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit_id ) {
			throw new \RuntimeException( 'Active Elementor kit not found.' );
		}
		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		$settings = is_array( $settings ) ? $settings : [];
		$existing = (array) ( $settings['custom_typography'] ?? [] );
		$by_id    = [];
		foreach ( $existing as $i => $row ) {
			if ( isset( $row['_id'] ) ) $by_id[ (string) $row['_id'] ] = $i;
		}
		foreach ( $typo as $t ) {
			$id = sanitize_text_field( (string) ( $t['_id'] ?? '' ) );
			if ( '' === $id ) continue;
			$entry = [];
			foreach ( self::ALLOWED as $k ) {
				if ( isset( $t[ $k ] ) ) $entry[ $k ] = $t[ $k ];
			}
			$entry['typography_typography'] = 'custom';
			if ( isset( $by_id[ $id ] ) ) {
				$existing[ $by_id[ $id ] ] = array_merge( $existing[ $by_id[ $id ] ], $entry );
			} else {
				$existing[] = $entry;
			}
		}
		$settings['custom_typography'] = array_values( $existing );
		update_post_meta( $kit_id, '_elementor_page_settings', $settings );
		return [ 'kit_id' => $kit_id, 'custom_typography' => $settings['custom_typography'] ];
	}
}
