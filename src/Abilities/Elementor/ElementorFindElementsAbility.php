<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorFindElementsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-find-elements'; }
	protected function meta(): array { return [
		'label' => __( 'Find Elementor elements', 'mcp-for-wordpress' ),
		'description' => __( 'Searches Elementor elements with filters for element type, widget type, settings key/value, or a "contains" string matched against the JSON. Returns up to "limit" matches with optional full element and path data.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [
			'post_id'         => [ 'type' => 'integer', 'minimum' => 1 ],
			'element_type'    => [ 'type' => 'string' ],
			'widget_type'     => [ 'type' => 'string' ],
			'settings_key'    => [ 'type' => 'string' ],
			'settings_value'  => [ 'type' => 'string' ],
			'contains'        => [ 'type' => 'string' ],
			'case_sensitive'  => [ 'type' => 'boolean', 'default' => false ],
			'limit'           => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 20 ],
			'include_element' => [ 'type' => 'boolean', 'default' => false ],
			'include_path'    => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'matches' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
		'total' => [ 'type' => 'integer' ], 'truncated' => [ 'type' => 'boolean' ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$element_type   = (string) ( $input['element_type'] ?? '' );
		$widget_type    = (string) ( $input['widget_type'] ?? '' );
		$settings_key   = (string) ( $input['settings_key'] ?? '' );
		$settings_value = isset( $input['settings_value'] ) ? (string) $input['settings_value'] : null;
		$contains       = (string) ( $input['contains'] ?? '' );
		$case_sensitive = ! empty( $input['case_sensitive'] );
		$limit          = isset( $input['limit'] ) ? max( 1, min( 200, (int) $input['limit'] ) ) : 20;
		$include_elem   = ! empty( $input['include_element'] );
		$include_path   = ! empty( $input['include_path'] );
		if ( '' === $element_type && '' === $widget_type && '' === $settings_key && null === $settings_value && '' === $contains ) {
			throw new \RuntimeException( 'Provide at least one filter to search for elements.' );
		}
		if ( null !== $settings_value && '' === $settings_key ) {
			throw new \RuntimeException( 'settings_key is required when settings_value is provided.' );
		}
		$match_text = function ( string $h, string $n ) use ( $case_sensitive ): bool {
			if ( '' === $n ) return true;
			return $case_sensitive ? ( false !== strpos( $h, $n ) ) : ( false !== stripos( $h, $n ) );
		};
		$page      = ElementorPage::load( (int) $input['post_id'] );
		$matches   = [];
		$truncated = false;
		$walk = function ( array $nodes, array $path ) use ( &$walk, &$matches, &$truncated, $element_type, $widget_type, $settings_key, $settings_value, $contains, $limit, $include_elem, $include_path, $match_text ): void {
			foreach ( $nodes as $n ) {
				if ( $truncated ) return;
				if ( ! is_array( $n ) ) continue;
				$nid = (string) ( $n['id'] ?? '' );
				$cur_path = $path;
				if ( '' !== $nid ) $cur_path[] = $nid;
				$ok = true;
				if ( '' !== $element_type && ( $n['elType'] ?? '' ) !== $element_type ) $ok = false;
				if ( $ok && '' !== $widget_type && ( $n['widgetType'] ?? '' ) !== $widget_type ) $ok = false;
				if ( $ok && '' !== $settings_key ) {
					$settings = is_array( $n['settings'] ?? null ) ? $n['settings'] : [];
					if ( ! array_key_exists( $settings_key, $settings ) ) $ok = false;
					elseif ( null !== $settings_value ) {
						$v = $settings[ $settings_key ];
						if ( is_array( $v ) || is_object( $v ) ) $v = wp_json_encode( $v );
						if ( ! $match_text( (string) $v, (string) $settings_value ) ) $ok = false;
					}
				}
				if ( $ok && '' !== $contains ) {
					$json = (string) wp_json_encode( $n );
					if ( ! $match_text( $json, $contains ) ) $ok = false;
				}
				if ( $ok ) {
					$row = [
						'element_id' => $nid,
						'elType'     => (string) ( $n['elType'] ?? '' ),
						'widgetType' => (string) ( $n['widgetType'] ?? '' ),
					];
					if ( $include_elem ) $row['element'] = $n;
					if ( $include_path ) $row['path'] = $cur_path;
					$matches[] = $row;
					if ( count( $matches ) >= $limit ) { $truncated = true; return; }
				}
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) ) $walk( $n['elements'], $cur_path );
			}
		};
		$walk( $page->data(), [] );
		return [ 'matches' => $matches, 'total' => count( $matches ), 'truncated' => $truncated ];
	}
}
