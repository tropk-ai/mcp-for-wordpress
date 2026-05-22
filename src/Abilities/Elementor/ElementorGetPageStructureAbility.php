<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorGetPageStructureAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-page-structure'; }
	protected function meta(): array { return [
		'label' => __( 'Get Elementor page structure', 'mcp-for-wordpress' ),
		'description' => __( 'Returns a lightweight element tree (IDs, types and a small settings summary) for an Elementor post. Heavy settings payloads are stripped for readability.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [
			'post_id' => [ 'type' => 'integer', 'minimum' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'post_id' => [ 'type' => 'integer' ], 'title' => [ 'type' => 'string' ],
		'type' => [ 'type' => 'string' ], 'structure' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$id   = (int) $input['post_id'];
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post ) {
			throw new \RuntimeException( sprintf( 'Post %d not found.', $id ) );
		}
		$key_fields = [ 'title', 'editor', 'text', 'image', 'link', 'html', 'header_size' ];
		$simplify = function ( array $elements ) use ( &$simplify, $key_fields ): array {
			$out = [];
			foreach ( $elements as $el ) {
				if ( ! is_array( $el ) ) continue;
				$item = [
					'id'     => (string) ( $el['id'] ?? '' ),
					'elType' => (string) ( $el['elType'] ?? '' ),
				];
				if ( ! empty( $el['widgetType'] ) ) $item['widgetType'] = (string) $el['widgetType'];
				$settings = is_array( $el['settings'] ?? null ) ? $el['settings'] : [];
				$summary  = [];
				foreach ( $key_fields as $f ) {
					if ( isset( $settings[ $f ] ) && '' !== $settings[ $f ] ) {
						$v = $settings[ $f ];
						if ( is_string( $v ) && strlen( $v ) > 100 ) $v = substr( $v, 0, 100 ) . '...';
						$summary[ $f ] = $v;
					}
				}
				if ( 'container' === ( $el['elType'] ?? '' ) ) {
					foreach ( [ 'flex_direction', 'content_width', 'container_type' ] as $f ) {
						if ( isset( $settings[ $f ] ) && '' !== $settings[ $f ] ) $summary[ $f ] = $settings[ $f ];
					}
				}
				if ( ! empty( $summary ) ) $item['settings_summary'] = $summary;
				if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
					$item['elements'] = $simplify( $el['elements'] );
				}
				$out[] = $item;
			}
			return $out;
		};
		$page = ElementorPage::load( $id );
		$doc_type = (string) get_post_meta( $id, '_elementor_template_type', true );
		return [
			'post_id'   => $id,
			'title'     => (string) $post->post_title,
			'type'      => $doc_type,
			'structure' => $simplify( $page->data() ),
		];
	}
}
