<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorImageWidgetToBackgroundContainerAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-image-widget-to-background-container'; }
	protected function meta(): array { return [
		'label' => __( 'Convert image widget to background container', 'mcp-for-wordpress' ),
		'description' => __( 'Replaces an image-widget-bearing container subtree with a native background-image container using the same media. Useful for 50/50 offer rows where the image needs to fill the container height.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'container_element_id' ],
		'properties'           => [
			'post_id'              => [ 'type' => 'integer', 'minimum' => 1 ],
			'container_element_id' => [ 'type' => 'string', 'minLength' => 1 ],
			'image_widget_id'      => [ 'type' => 'string' ],
			'background_size'      => [ 'type' => 'string', 'default' => 'cover' ],
			'background_position'  => [ 'type' => 'string', 'default' => 'center center' ],
			'background_repeat'    => [ 'type' => 'string', 'default' => 'no-repeat' ],
			'zero_padding'         => [ 'type' => 'boolean', 'default' => true ],
			'spacer_size'          => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
			'dry_run'              => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'updated' => [ 'type' => 'boolean' ], 'media' => [ 'type' => [ 'object', 'null' ] ],
		'image_widget_id' => [ 'type' => 'string' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['post_id'] ?? 0 );
		if ( ! empty( $input['dry_run'] ) ) return current_user_can( 'edit_post', $id );
		return current_user_can( 'edit_post', $id ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$id           = (int) $input['post_id'];
		$container_id = (string) $input['container_element_id'];
		$dry_run      = ! empty( $input['dry_run'] );
		$page         = ElementorPage::load( $id );
		$container    = $page->find_widget( $container_id );
		if ( null === $container ) {
			throw new \RuntimeException( sprintf( 'Container "%s" not found.', $container_id ) );
		}
		if ( 'container' !== ( $container['elType'] ?? '' ) ) {
			throw new \RuntimeException( 'Target element is not a container.' );
		}
		// Locate image widget.
		$find_image = function ( array $node ) use ( &$find_image ): ?array {
			if ( ( $node['widgetType'] ?? '' ) === 'image' || ( $node['widgetType'] ?? '' ) === 'theme-post-featured-image' ) {
				return $node;
			}
			foreach ( (array) ( $node['elements'] ?? [] ) as $c ) {
				if ( is_array( $c ) ) {
					$r = $find_image( $c );
					if ( null !== $r ) return $r;
				}
			}
			return null;
		};
		$image = null;
		$widget_id_filter = isset( $input['image_widget_id'] ) ? (string) $input['image_widget_id'] : '';
		if ( '' !== $widget_id_filter ) {
			$find_by_id = function ( array $node ) use ( &$find_by_id, $widget_id_filter ): ?array {
				if ( ( $node['id'] ?? '' ) === $widget_id_filter ) return $node;
				foreach ( (array) ( $node['elements'] ?? [] ) as $c ) {
					if ( is_array( $c ) ) { $r = $find_by_id( $c ); if ( null !== $r ) return $r; }
				}
				return null;
			};
			$image = $find_by_id( $container );
		}
		if ( null === $image ) $image = $find_image( $container );
		if ( null === $image ) {
			throw new \RuntimeException( 'No image widget found inside the container subtree.' );
		}
		$settings = is_array( $image['settings'] ?? null ) ? $image['settings'] : [];
		$media    = is_array( $settings['image'] ?? null ) ? $settings['image'] : null;
		$url      = is_array( $media ) ? (string) ( $media['url'] ?? '' ) : '';
		$attach   = is_array( $media ) ? (int) ( $media['id'] ?? 0 ) : 0;
		if ( '' === $url ) {
			throw new \RuntimeException( 'Failed to resolve media URL from the image widget.' );
		}
		$original_settings = is_array( $container['settings'] ?? null ) ? $container['settings'] : [];
		$new_settings = $original_settings;
		$new_settings['_title']                = is_string( $new_settings['_title'] ?? null ) ? $new_settings['_title'] : 'background image';
		$new_settings['content_width']         = $new_settings['content_width']         ?? 'full';
		$new_settings['flex_direction']        = $new_settings['flex_direction']        ?? 'column';
		$new_settings['flex_justify_content']  = $new_settings['flex_justify_content']  ?? 'center';
		$new_settings['flex_align_items']      = $new_settings['flex_align_items']      ?? 'center';
		$new_settings['background_background'] = 'classic';
		$new_settings['background_image']      = [ 'url' => $url, 'id' => $attach ];
		$new_settings['background_position']   = (string) ( $input['background_position'] ?? 'center center' );
		$new_settings['background_size']       = (string) ( $input['background_size']     ?? 'cover' );
		$new_settings['background_repeat']     = (string) ( $input['background_repeat']   ?? 'no-repeat' );
		$new_settings['_flex_size']            = $new_settings['_flex_size']     ?? 'none';
		$new_settings['_element_width']        = $new_settings['_element_width'] ?? 'initial';
		if ( ! array_key_exists( 'zero_padding', $input ) || ! empty( $input['zero_padding'] ) ) {
			$new_settings['padding'] = [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ];
		}
		$spacer_size = max( 1, (int) ( $input['spacer_size'] ?? 1 ) );
		$replacement = [
			'id'       => $container_id,
			'elType'   => 'container',
			'isInner'  => ! empty( $container['isInner'] ),
			'settings' => $new_settings,
			'elements' => [
				[
					'id'         => $container_id . '_bg_spacer',
					'elType'     => 'widget',
					'widgetType' => 'spacer',
					'settings'   => [ 'space' => [ 'unit' => 'px', 'size' => $spacer_size ] ],
					'elements'   => [],
				],
			],
		];
		$out_media = [ 'url' => $url, 'id' => $attach ];
		if ( $replacement === $container ) {
			return [ 'updated' => false, 'media' => $out_media, 'image_widget_id' => (string) ( $image['id'] ?? '' ), 'snapshot_id' => null ];
		}
		if ( $dry_run ) {
			return [ 'updated' => false, 'media' => $out_media, 'image_widget_id' => (string) ( $image['id'] ?? '' ), 'snapshot_id' => null ];
		}
		$data = $page->data();
		$replace = function ( array &$nodes ) use ( &$replace, $container_id, $replacement ): bool {
			foreach ( $nodes as $i => &$n ) {
				if ( ! is_array( $n ) ) continue;
				if ( ( $n['id'] ?? '' ) === $container_id ) { $nodes[ $i ] = $replacement; return true; }
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) && $replace( $n['elements'] ) ) return true;
			}
			return false;
		};
		$replace( $data );
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-image-widget-to-background-container' );
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		update_post_meta( $id, '_elementor_data', wp_slash( (string) $json ) );
		ElementorPage::load( $id )->flush_css();
		return [ 'updated' => true, 'media' => $out_media, 'image_widget_id' => (string) ( $image['id'] ?? '' ), 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
