<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorBuildPageAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-build-page'; }
	protected function meta(): array { return [
		'label' => __( 'Build an Elementor page', 'mcp-for-wordpress' ),
		'description' => __( 'Creates a new Elementor page from a declarative structure of containers and widgets in a single call. Children of a row container are auto-sized to equal percentage widths.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'title', 'structure' ],
		'properties'           => [
			'title'         => [ 'type' => 'string', 'minLength' => 1 ],
			'status'        => [ 'type' => 'string', 'enum' => [ 'draft', 'publish', 'pending', 'private' ], 'default' => 'draft' ],
			'post_type'     => [ 'type' => 'string', 'default' => 'page' ],
			'page_settings' => [ 'type' => 'object' ],
			'structure'     => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'post_id' => [ 'type' => 'integer' ], 'title' => [ 'type' => 'string' ],
		'edit_url' => [ 'type' => 'string' ], 'preview_url' => [ 'type' => 'string' ],
		'elements_created' => [ 'type' => 'integer' ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		$pt   = (string) ( $input['post_type'] ?? 'page' );
		$pto  = get_post_type_object( $pt );
		$cap  = $pto && isset( $pto->cap->create_posts ) ? (string) $pto->cap->create_posts : 'edit_posts';
		return current_user_can( $cap ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$title     = (string) $input['title'];
		$status    = (string) ( $input['status'] ?? 'draft' );
		$post_type = (string) ( $input['post_type'] ?? 'page' );
		$structure = (array) $input['structure'];
		$ps        = is_array( $input['page_settings'] ?? null ) ? $input['page_settings'] : null;

		$created = 0;
		$new_id = function (): string {
			try { return bin2hex( random_bytes( 4 ) ); }
			catch ( \Throwable $e ) { return substr( md5( uniqid( '', true ) ), 0, 8 ); }
		};
		$build = function ( array $items, string $parent_direction = '' ) use ( &$build, &$created, $new_id ): array {
			$out = [];
			$is_row = ( 'row' === $parent_direction || 'row-reverse' === $parent_direction );
			$count  = count( $items );
			$equal  = ( $is_row && $count > 1 ) ? round( 100 / $count, 2 ) : null;
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) continue;
				$type = (string) ( $item['type'] ?? '' );
				$settings = is_array( $item['settings'] ?? null ) ? $item['settings'] : [];
				if ( 'container' === $type ) {
					$direction = (string) ( $settings['flex_direction'] ?? '' );
					if ( null !== $equal && ! isset( $settings['width'] ) && ! isset( $settings['_flex_size'] ) && ! isset( $settings['_flex_grow'] ) ) {
						$settings['content_width'] = 'full';
						$settings['width'] = [ 'unit' => '%', 'size' => $equal ];
					}
					$kids = is_array( $item['children'] ?? null ) ? $item['children'] : [];
					$node = [
						'id'       => $new_id(),
						'elType'   => 'container',
						'settings' => $settings,
						'elements' => $build( $kids, $direction ),
					];
					$created++;
					$out[] = $node;
				} elseif ( 'widget' === $type ) {
					$widget_type = (string) ( $item['widget_type'] ?? '' );
					if ( '' === $widget_type ) continue;
					$widget = [
						'id'         => $new_id(),
						'elType'     => 'widget',
						'widgetType' => $widget_type,
						'settings'   => $settings,
						'elements'   => [],
					];
					$created++;
					if ( null !== $equal ) {
						// Wrap widgets in row in equal-width column.
						$col = [
							'id'       => $new_id(),
							'elType'   => 'container',
							'isInner'  => true,
							'settings' => [
								'content_width' => 'full',
								'width'         => [ 'unit' => '%', 'size' => $equal ],
							],
							'elements' => [ $widget ],
						];
						$created++;
						$out[] = $col;
					} else {
						$out[] = $widget;
					}
				}
			}
			return $out;
		};

		$post_id = wp_insert_post(
			[
				'post_title'  => $title,
				'post_status' => $status,
				'post_type'   => $post_type,
				'meta_input'  => [
					'_elementor_edit_mode'     => 'builder',
					'_elementor_template_type' => 'wp-' . $post_type,
				],
			],
			true
		);
		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( $post_id->get_error_message() );
		}
		$elements = $build( $structure );
		$json     = wp_json_encode( $elements, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		update_post_meta( (int) $post_id, '_elementor_data', wp_slash( (string) $json ) );
		if ( null !== $ps ) {
			update_post_meta( (int) $post_id, '_elementor_page_settings', $ps );
		}
		ElementorPage::load( (int) $post_id )->flush_css();
		return [
			'post_id'          => (int) $post_id,
			'title'            => $title,
			'edit_url'         => (string) admin_url( 'post.php?post=' . (int) $post_id . '&action=elementor' ),
			'preview_url'      => (string) ( get_permalink( (int) $post_id ) ?: '' ),
			'elements_created' => $created,
		];
	}
}
