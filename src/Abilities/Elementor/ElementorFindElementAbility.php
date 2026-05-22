<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorFindElementAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-find-element'; }
	protected function meta(): array { return [
		'label' => __( 'Find an Elementor element', 'mcp-for-wordpress' ),
		'description' => __( 'Searches the element tree for matches by widget_type, element_type, settings key/value, or free-text in settings string values. Returns lightweight matches with a settings preview.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [
			'post_id'       => [ 'type' => 'integer', 'minimum' => 1 ],
			'widget_type'   => [ 'type' => 'string' ],
			'element_type'  => [ 'type' => 'string', 'enum' => [ 'container', 'widget', 'section', 'column' ] ],
			'search_text'   => [ 'type' => 'string' ],
			'setting_key'   => [ 'type' => 'string' ],
			'setting_value' => [ 'type' => 'string' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'matches' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ], 'count' => [ 'type' => 'integer' ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page          = ElementorPage::load( (int) $input['post_id'] );
		$widget_type   = (string) ( $input['widget_type'] ?? '' );
		$element_type  = (string) ( $input['element_type'] ?? '' );
		$search_text   = (string) ( $input['search_text'] ?? '' );
		$setting_key   = (string) ( $input['setting_key'] ?? '' );
		$setting_value = $input['setting_value'] ?? null;
		$matches       = [];
		$walk = function ( array $nodes ) use ( &$walk, &$matches, $widget_type, $element_type, $search_text, $setting_key, $setting_value ): void {
			foreach ( $nodes as $n ) {
				if ( ! is_array( $n ) ) continue;
				$el = (string) ( $n['elType'] ?? '' );
				$wt = (string) ( $n['widgetType'] ?? '' );
				$settings = is_array( $n['settings'] ?? null ) ? $n['settings'] : [];
				$ok = true;
				if ( '' !== $element_type && $el !== $element_type ) $ok = false;
				if ( $ok && '' !== $widget_type && $wt !== $widget_type ) $ok = false;
				if ( $ok && '' !== $setting_key ) {
					if ( ! array_key_exists( $setting_key, $settings ) ) $ok = false;
					elseif ( null !== $setting_value && (string) ( $settings[ $setting_key ] ?? '' ) !== (string) $setting_value ) $ok = false;
				}
				if ( $ok && '' !== $search_text ) {
					$needle = strtolower( $search_text );
					$found = false;
					foreach ( $settings as $v ) {
						if ( is_string( $v ) && false !== stripos( strtolower( $v ), $needle ) ) { $found = true; break; }
					}
					if ( ! $found ) $ok = false;
				}
				if ( $ok ) {
					$preview = [];
					$c = 0;
					foreach ( $settings as $k => $v ) {
						if ( $c >= 5 ) break;
						if ( is_string( $v ) && '' !== $v ) {
							$preview[ $k ] = mb_strlen( $v ) > 100 ? mb_substr( $v, 0, 100 ) . '…' : $v;
							$c++;
						}
					}
					$matches[] = [
						'element_id'       => (string) ( $n['id'] ?? '' ),
						'elType'           => $el,
						'widgetType'       => $wt,
						'settings_preview' => $preview,
					];
				}
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) ) $walk( $n['elements'] );
			}
		};
		$walk( $page->data() );
		return [ 'matches' => $matches, 'count' => count( $matches ) ];
	}
}
