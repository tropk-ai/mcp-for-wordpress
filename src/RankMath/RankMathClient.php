<?php
declare(strict_types=1);

namespace Tropk\Mcp\RankMath;

/**
 * Thin adapter over Rank Math's postmeta + REST surface. When the
 * Headless CMS Support option is enabled the client routes writes
 * through /wp-json/rankmath/v1/updateMeta and updateSchemas so that
 * Rank Math performs its own variable resolution and sanitization;
 * otherwise it falls back to direct update_post_meta calls.
 */
final class RankMathClient {

	public const META_KEYS = [
		'title'                      => 'rank_math_title',
		'description'                => 'rank_math_description',
		'focus_keyword'              => 'rank_math_focus_keyword',
		'canonical_url'              => 'rank_math_canonical_url',
		'robots'                     => 'rank_math_robots',
		'advanced_robots'            => 'rank_math_advanced_robots',
		'breadcrumb_title'           => 'rank_math_breadcrumb_title',
		'pillar_content'             => 'rank_math_pillar_content',
		'facebook_title'             => 'rank_math_facebook_title',
		'facebook_description'       => 'rank_math_facebook_description',
		'facebook_image'             => 'rank_math_facebook_image',
		'facebook_image_id'          => 'rank_math_facebook_image_id',
		'twitter_title'              => 'rank_math_twitter_title',
		'twitter_description'        => 'rank_math_twitter_description',
		'twitter_image'              => 'rank_math_twitter_image',
		'twitter_card_type'          => 'rank_math_twitter_card_type',
		'twitter_use_facebook'       => 'rank_math_twitter_use_facebook',
	];

	public static function is_active(): bool {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( '\\RankMath\\Helper' ) || function_exists( 'rank_math' );
	}

	public static function headless_support_enabled(): bool {
		$general = get_option( 'rank-math-options-general' );
		if ( is_array( $general ) && ! empty( $general['headless_support'] ) && 'on' === $general['headless_support'] ) {
			return true;
		}
		return (bool) get_option( 'rank_math_headless_support', false );
	}

	/**
	 * @param array<int, string>|null $fields Subset of META_KEYS keys to return; defaults to all.
	 * @return array<string, mixed>
	 */
	public function get_meta( int $post_id, ?array $fields = null ): array {
		$keys = null === $fields ? array_keys( self::META_KEYS ) : array_values( array_intersect( $fields, array_keys( self::META_KEYS ) ) );
		$out  = [];
		foreach ( $keys as $alias ) {
			$meta_key       = self::META_KEYS[ $alias ];
			$out[ $alias ]  = get_post_meta( $post_id, $meta_key, true );
		}

		$post = get_post( $post_id );
		if ( $post instanceof \WP_Post ) {
			foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
				$value = get_post_meta( $post_id, 'rank_math_primary_' . $taxonomy, true );
				if ( '' !== $value && null !== $value ) {
					$out[ 'primary_' . $taxonomy ] = (int) $value;
				}
			}
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $fields Alias => value (use META_KEYS aliases). Pass null to delete.
	 * @return array<string, mixed>
	 */
	public function update_meta( int $post_id, array $fields ): array {
		$payload = [];
		foreach ( $fields as $alias => $value ) {
			if ( str_starts_with( (string) $alias, 'primary_' ) ) {
				$payload[ 'rank_math_' . $alias ] = $value;
				continue;
			}
			if ( ! isset( self::META_KEYS[ $alias ] ) ) {
				continue;
			}
			$payload[ self::META_KEYS[ $alias ] ] = $value;
		}

		if ( [] === $payload ) {
			return [ 'updated' => [], 'deleted' => [] ];
		}

		if ( self::headless_support_enabled() && function_exists( 'rest_do_request' ) ) {
			$rest_result = $this->dispatch_rest( 'updateMeta', [
				'objectID'   => $post_id,
				'objectType' => 'post',
				'meta'       => $payload,
			] );
			if ( null !== $rest_result ) {
				return $rest_result;
			}
		}

		$updated = [];
		$deleted = [];
		foreach ( $payload as $key => $value ) {
			if ( null === $value ) {
				delete_post_meta( $post_id, $key );
				$deleted[] = $key;
			} else {
				update_post_meta( $post_id, $key, $value );
				$updated[] = $key;
			}
		}
		return [ 'updated' => $updated, 'deleted' => $deleted ];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_schemas( int $post_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
				$post_id,
				$wpdb->esc_like( 'rank_math_schema_' ) . '%'
			),
			ARRAY_A
		);

		$out = [];
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$type = (string) substr( (string) $row['meta_key'], strlen( 'rank_math_schema_' ) );
			if ( '' === $type ) {
				continue;
			}
			$value           = maybe_unserialize( (string) $row['meta_value'] );
			$out[ $type ]    = $value;
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $schema
	 */
	public function update_schema( int $post_id, string $schema_type, array $schema ): bool {
		if ( '' === $schema_type ) {
			throw new \RuntimeException( 'Schema type is required.' );
		}

		if ( self::headless_support_enabled() && function_exists( 'rest_do_request' ) ) {
			$result = $this->dispatch_rest( 'updateSchemas', [
				'objectID'   => $post_id,
				'objectType' => 'post',
				'schemas'    => [ $schema_type => $schema ],
			] );
			if ( null !== $result ) {
				return true;
			}
		}

		update_post_meta( $post_id, 'rank_math_schema_' . $schema_type, $schema );
		return true;
	}

	public function delete_schema( int $post_id, string $schema_type ): bool {
		if ( '' === $schema_type ) {
			throw new \RuntimeException( 'Schema type is required.' );
		}
		delete_post_meta( $post_id, 'rank_math_schema_' . $schema_type );
		return true;
	}

	public function get_head( string $url ): ?string {
		if ( '' === $url ) {
			return null;
		}
		if ( ! function_exists( 'rest_do_request' ) ) {
			return null;
		}
		$request = new \WP_REST_Request( 'GET', '/rankmath/v1/getHead' );
		$request->set_query_params( [ 'url' => $url ] );
		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			return null;
		}
		$data = $response->get_data();
		if ( is_array( $data ) && isset( $data['head'] ) ) {
			return (string) $data['head'];
		}
		if ( is_string( $data ) ) {
			return $data;
		}
		return null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function dispatch_rest( string $endpoint, array $body ): ?array {
		$request = new \WP_REST_Request( 'POST', '/rankmath/v1/' . $endpoint );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) ?: '' );
		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			return null;
		}
		$data = $response->get_data();
		return is_array( $data ) ? $data : [ 'data' => $data ];
	}
}
