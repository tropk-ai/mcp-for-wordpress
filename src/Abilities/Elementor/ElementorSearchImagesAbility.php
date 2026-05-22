<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorSearchImagesAbility extends AbstractAbility {
	private const ENDPOINT = 'https://api.openverse.engineering/v1/images/';

	public function slug(): string { return 'elementor-search-images'; }
	protected function meta(): array { return [
		'label'       => __( 'Search Creative Commons stock images', 'mcp-for-wordpress' ),
		'description' => __( 'Searches the Openverse API (Creative Commons licensed images) and returns URLs, thumbnails, licensing, and attribution.', 'mcp-for-wordpress' ),
		'readonly'    => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'query' ],
		'properties'           => [
			'query'        => [ 'type' => 'string', 'minLength' => 1 ],
			'page'         => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
			'page_size'    => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 5 ],
			'license'      => [ 'type' => 'string', 'enum' => [ 'by', 'by-sa', 'by-nc', 'cc0', 'pdm' ] ],
			'aspect_ratio' => [ 'type' => 'string', 'enum' => [ 'tall', 'wide', 'square' ] ],
			'size'         => [ 'type' => 'string', 'enum' => [ 'small', 'medium', 'large' ] ],
			'category'     => [ 'type' => 'string', 'enum' => [ 'photograph', 'illustration', 'digitized_artwork' ] ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'result_count' => [ 'type' => 'integer' ], 'page' => [ 'type' => 'integer' ],
		'page_count' => [ 'type' => 'integer' ], 'results' => [ 'type' => 'array' ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$params = [ 'q' => (string) ( $input['query'] ?? '' ) ];
		foreach ( [ 'page', 'page_size', 'license', 'aspect_ratio', 'size', 'category' ] as $k ) {
			if ( isset( $input[ $k ] ) ) $params[ $k ] = $input[ $k ];
		}
		$url = self::ENDPOINT . '?' . http_build_query( $params );
		$res = wp_remote_get( $url, [ 'timeout' => 20, 'headers' => [ 'Accept' => 'application/json' ] ] );
		if ( is_wp_error( $res ) ) {
			throw new \RuntimeException( $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( sprintf( 'Openverse API returned HTTP %d.', $code ) );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $body ) ) {
			throw new \RuntimeException( 'Invalid Openverse response.' );
		}
		$out = [];
		foreach ( (array) ( $body['results'] ?? [] ) as $img ) {
			$out[] = [
				'id'                  => (string) ( $img['id'] ?? '' ),
				'title'               => (string) ( $img['title'] ?? '' ),
				'url'                 => (string) ( $img['url'] ?? '' ),
				'thumbnail'           => (string) ( $img['thumbnail'] ?? '' ),
				'width'               => (int) ( $img['width'] ?? 0 ),
				'height'              => (int) ( $img['height'] ?? 0 ),
				'creator'             => (string) ( $img['creator'] ?? '' ),
				'creator_url'         => (string) ( $img['creator_url'] ?? '' ),
				'license'             => (string) ( $img['license'] ?? '' ),
				'license_url'         => (string) ( $img['license_url'] ?? '' ),
				'attribution'         => (string) ( $img['attribution'] ?? '' ),
				'source'              => (string) ( $img['source'] ?? '' ),
				'foreign_landing_url' => (string) ( $img['foreign_landing_url'] ?? '' ),
			];
		}
		return [
			'result_count' => (int) ( $body['result_count'] ?? 0 ),
			'page'         => (int) ( $body['page'] ?? 1 ),
			'page_count'   => (int) ( $body['page_count'] ?? 0 ),
			'results'      => $out,
		];
	}
}
