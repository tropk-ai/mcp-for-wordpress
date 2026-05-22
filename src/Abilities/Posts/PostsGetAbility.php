<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Posts;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;

final class PostsGetAbility implements Ability {

	public function slug(): string {
		return 'posts-get';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Get a post', 'mcp-for-wordpress' ),
			'description'         => __( 'Returns a single post with content, taxonomies, and selected postmeta. Read-only.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'id' ],
				'properties'           => [
					'id'                 => [ 'type' => 'integer', 'minimum' => 1 ],
					'include_content'    => [ 'type' => 'boolean', 'default' => true ],
					'include_meta_keys'  => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'include_taxonomies' => [ 'type' => 'boolean', 'default' => true ],
				],
			],
			'output_schema'       => [
				'$schema' => 'https://json-schema.org/draft/2020-12/schema',
				'type'    => 'object',
			],
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations'  => [ 'readonly' => true, 'idempotent' => true ],
				'show_in_rest' => true,
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['id'] ?? 0 );
		return $id > 0 && current_user_can( 'read_post', $id );
	}

	public function execute( array $input ): array {
		$post = get_post( (int) $input['id'] );
		if ( ! $post instanceof \WP_Post ) {
			throw new \RuntimeException( sprintf( 'Post %d not found.', (int) $input['id'] ) );
		}

		$out = [
			'id'        => (int) $post->ID,
			'title'     => (string) $post->post_title,
			'slug'      => (string) $post->post_name,
			'status'    => (string) $post->post_status,
			'post_type' => (string) $post->post_type,
			'date'      => (string) $post->post_date_gmt,
			'modified'  => (string) $post->post_modified_gmt,
			'permalink' => (string) get_permalink( $post ),
			'author_id' => (int) $post->post_author,
			'parent'    => (int) $post->post_parent,
			'excerpt'   => (string) get_the_excerpt( $post ),
		];

		if ( false !== ( $input['include_content'] ?? true ) ) {
			$out['content'] = (string) $post->post_content;
		}

		if ( false !== ( $input['include_taxonomies'] ?? true ) ) {
			$tax_out = [];
			foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
				$terms = wp_get_object_terms( $post->ID, $taxonomy, [ 'fields' => 'id=>name' ] );
				if ( is_wp_error( $terms ) ) {
					continue;
				}
				$tax_out[ $taxonomy ] = [];
				foreach ( $terms as $term_id => $name ) {
					$tax_out[ $taxonomy ][] = [ 'id' => (int) $term_id, 'name' => (string) $name ];
				}
			}
			$out['taxonomies'] = $tax_out;
		}

		if ( isset( $input['include_meta_keys'] ) && is_array( $input['include_meta_keys'] ) ) {
			$meta_out = [];
			foreach ( $input['include_meta_keys'] as $key ) {
				if ( ! is_string( $key ) || '' === $key ) {
					continue;
				}
				$meta_out[ $key ] = get_post_meta( $post->ID, $key, true );
			}
			$out['meta'] = $meta_out;
		}

		return $out;
	}
}
