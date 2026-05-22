<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorAddStockImageAbility extends AbstractAbility {
	private const ENDPOINT = 'https://api.openverse.engineering/v1/images/';

	public function slug(): string { return 'elementor-add-stock-image'; }
	protected function meta(): array { return [
		'label'       => __( 'Add stock image as Elementor image widget', 'mcp-for-wordpress' ),
		'description' => __( 'Searches Openverse, sideloads the chosen image into the Media Library, then appends an image widget to the given parent container. Defaults to landscape ("wide") images.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'parent_id', 'query' ],
		'properties'           => [
			'post_id'      => [ 'type' => 'integer', 'minimum' => 1 ],
			'parent_id'    => [ 'type' => 'string', 'minLength' => 1 ],
			'query'        => [ 'type' => 'string', 'minLength' => 1 ],
			'index'        => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0 ],
			'aspect_ratio' => [ 'type' => 'string', 'enum' => [ 'wide', 'tall', 'square', 'any' ], 'default' => 'wide' ],
			'image_size'   => [ 'type' => 'string', 'default' => 'full' ],
			'align'        => [ 'type' => 'string', 'enum' => [ 'left', 'center', 'right' ] ],
			'caption'      => [ 'type' => 'string' ],
			'alt_text'     => [ 'type' => 'string' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'post_id' => [ 'type' => 'integer' ], 'element_id' => [ 'type' => 'string' ],
		'attachment_id' => [ 'type' => 'integer' ], 'image_url' => [ 'type' => 'string' ],
		'attribution' => [ 'type' => 'string' ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['post_id'] ?? 0 );
		return $id > 0 && current_user_can( 'edit_post', $id ) && current_user_can( 'upload_files' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$post_id = (int) $input['post_id'];
		$parent  = (string) $input['parent_id'];
		$query   = (string) $input['query'];
		$index   = max( 0, (int) ( $input['index'] ?? 0 ) );
		$aspect  = (string) ( $input['aspect_ratio'] ?? 'wide' );
		if ( ! ElementorPage::is_elementor_post( $post_id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $post_id ) );
		}
		$params = [ 'q' => $query, 'page_size' => max( $index + 3, 5 ) ];
		if ( 'any' !== $aspect ) $params['aspect_ratio'] = $aspect;
		$res = wp_remote_get( self::ENDPOINT . '?' . http_build_query( $params ), [ 'timeout' => 20, 'headers' => [ 'Accept' => 'application/json' ] ] );
		if ( is_wp_error( $res ) ) {
			throw new \RuntimeException( $res->get_error_message() );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$results = is_array( $body ) ? (array) ( $body['results'] ?? [] ) : [];
		if ( empty( $results[ $index ] ) ) {
			throw new \RuntimeException( sprintf( 'No image at index %d for "%s".', $index, $query ) );
		}
		$img = (array) $results[ $index ];
		$src = (string) ( $img['url'] ?? '' );
		if ( '' === $src ) {
			throw new \RuntimeException( 'Image result missing URL.' );
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$tmp = download_url( $src, 30 );
		if ( is_wp_error( $tmp ) ) {
			throw new \RuntimeException( $tmp->get_error_message() );
		}
		$path = wp_parse_url( $src, PHP_URL_PATH );
		$name = $path ? basename( (string) $path ) : 'image.jpg';
		if ( ! preg_match( '/\.\w+$/', $name ) ) $name .= '.jpg';
		$attach_id = media_handle_sideload( [ 'name' => sanitize_file_name( $name ), 'tmp_name' => $tmp ], 0 );
		if ( is_wp_error( $attach_id ) ) {
			if ( file_exists( $tmp ) ) wp_delete_file( $tmp );
			throw new \RuntimeException( $attach_id->get_error_message() );
		}
		$attach_id = (int) $attach_id;
		$attribution = (string) ( $img['attribution'] ?? '' );
		$alt = sanitize_text_field( (string) ( $input['alt_text'] ?? ( $img['title'] ?? $query ) ) );
		if ( '' !== $alt ) update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt );
		if ( '' !== $attribution ) wp_update_post( [ 'ID' => $attach_id, 'post_excerpt' => $attribution ] );
		$local_url = (string) ( wp_get_attachment_url( $attach_id ) ?: '' );
		$settings = [ 'image' => [ 'url' => $local_url, 'id' => $attach_id ], 'image_size' => (string) ( $input['image_size'] ?? 'full' ) ];
		if ( ! empty( $input['align'] ) ) $settings['align'] = (string) $input['align'];
		$cap = sanitize_text_field( (string) ( $input['caption'] ?? '' ) );
		if ( '' !== $cap ) {
			$settings['caption_source'] = 'custom';
			$settings['caption']        = $cap;
		} elseif ( '' !== $attribution ) {
			$settings['caption_source'] = 'custom';
			$settings['caption']        = $attribution;
		}
		$new_id = self::random_id();
		$page = ElementorPage::load( $post_id );
		$data = $page->data();
		$inserted = self::insert( $data, $parent, [
			'id' => $new_id, 'elType' => 'widget', 'widgetType' => 'image', 'settings' => $settings, 'elements' => [],
		] );
		if ( ! $inserted ) {
			throw new \RuntimeException( sprintf( 'Parent element "%s" not found.', $parent ) );
		}
		$encoded = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		update_post_meta( $post_id, '_elementor_data', wp_slash( (string) $encoded ) );
		delete_post_meta( $post_id, '_elementor_css' );
		return [
			'post_id'       => $post_id,
			'element_id'    => $new_id,
			'attachment_id' => $attach_id,
			'image_url'     => $local_url,
			'attribution'   => $attribution,
		];
	}
	private static function insert( array &$nodes, string $parent_id, array $widget ): bool {
		foreach ( $nodes as &$n ) {
			if ( ! is_array( $n ) ) continue;
			if ( ( $n['id'] ?? '' ) === $parent_id ) {
				if ( ! isset( $n['elements'] ) || ! is_array( $n['elements'] ) ) $n['elements'] = [];
				$n['elements'][] = $widget;
				return true;
			}
			if ( isset( $n['elements'] ) && is_array( $n['elements'] ) ) {
				if ( self::insert( $n['elements'], $parent_id, $widget ) ) return true;
			}
		}
		unset( $n );
		return false;
	}
	private static function random_id(): string {
		try { return bin2hex( random_bytes( 4 ) ); } catch ( \Throwable $e ) { return substr( md5( uniqid( '', true ) ), 0, 8 ); }
	}
}
