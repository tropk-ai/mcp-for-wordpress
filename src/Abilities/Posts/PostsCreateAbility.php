<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Posts;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;

final class PostsCreateAbility implements Ability {

	public function slug(): string {
		return 'posts-create';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Create a post', 'mcp-for-wordpress' ),
			'description'         => __( 'Creates a new post / page / CPT. Supports dry_run for input validation without writing.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'post_type', 'title' ],
				'properties'           => [
					'post_type'  => [ 'type' => 'string', 'default' => 'post' ],
					'title'      => [ 'type' => 'string', 'minLength' => 1 ],
					'content'    => [ 'type' => 'string' ],
					'excerpt'    => [ 'type' => 'string' ],
					'status'     => [ 'type' => 'string', 'enum' => [ 'draft', 'pending', 'private', 'publish', 'future' ], 'default' => 'draft' ],
					'slug'       => [ 'type' => 'string' ],
					'author_id'  => [ 'type' => 'integer', 'minimum' => 1 ],
					'parent_id'  => [ 'type' => 'integer', 'minimum' => 0 ],
					'menu_order' => [ 'type' => 'integer' ],
					'date'       => [ 'type' => 'string' ],
					'meta'       => [ 'type' => 'object' ],
					'terms'      => [ 'type' => 'object' ],
					'dry_run'    => [ 'type' => 'boolean', 'default' => false ],
				],
			],
			'output_schema'       => [
				'$schema'    => 'https://json-schema.org/draft/2020-12/schema',
				'type'       => 'object',
				'properties' => [
					'created'   => [ 'type' => 'boolean' ],
					'dry_run'   => [ 'type' => 'boolean' ],
					'post_id'   => [ 'type' => [ 'integer', 'null' ] ],
					'permalink' => [ 'type' => [ 'string', 'null' ] ],
				],
				'required'   => [ 'created', 'dry_run' ],
			],
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations'  => [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ],
				'show_in_rest' => true,
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		$type_object = get_post_type_object( (string) ( $input['post_type'] ?? 'post' ) );
		if ( ! $type_object ) {
			return false;
		}
		$cap = (string) ( $type_object->cap->create_posts ?? $type_object->cap->edit_posts ?? 'edit_posts' );
		$status = (string) ( $input['status'] ?? 'draft' );
		if ( in_array( $status, [ 'publish', 'future', 'private' ], true ) ) {
			$publish_cap = (string) ( $type_object->cap->publish_posts ?? 'publish_posts' );
			if ( ! current_user_can( $publish_cap ) ) {
				return false;
			}
		}
		return current_user_can( $cap );
	}

	public function execute( array $input ): array {
		$post_type = (string) ( $input['post_type'] ?? 'post' );
		if ( ! post_type_exists( $post_type ) ) {
			throw new \RuntimeException( sprintf( 'Unknown post type "%s".', $post_type ) );
		}
		$dry_run = (bool) ( $input['dry_run'] ?? false );

		$args = [
			'post_type'    => $post_type,
			'post_title'   => (string) $input['title'],
			'post_content' => (string) ( $input['content'] ?? '' ),
			'post_excerpt' => (string) ( $input['excerpt'] ?? '' ),
			'post_status'  => (string) ( $input['status'] ?? 'draft' ),
		];
		foreach ( [ 'slug' => 'post_name', 'author_id' => 'post_author', 'parent_id' => 'post_parent', 'menu_order' => 'menu_order', 'date' => 'post_date_gmt' ] as $in => $out ) {
			if ( isset( $input[ $in ] ) ) {
				$args[ $out ] = 'post_name' === $out ? sanitize_title( (string) $input[ $in ] ) : $input[ $in ];
			}
		}

		if ( $dry_run ) {
			return [ 'created' => false, 'dry_run' => true, 'post_id' => null, 'permalink' => null ];
		}

		$post_id = wp_insert_post( wp_slash( $args ), true );
		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( 'wp_insert_post failed: ' . $post_id->get_error_message() );
		}

		if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
			foreach ( $input['meta'] as $key => $value ) {
				if ( is_string( $key ) && '' !== $key ) {
					update_post_meta( (int) $post_id, $key, $value );
				}
			}
		}
		if ( isset( $input['terms'] ) && is_array( $input['terms'] ) ) {
			foreach ( $input['terms'] as $taxonomy => $terms ) {
				if ( is_string( $taxonomy ) && taxonomy_exists( $taxonomy ) ) {
					wp_set_object_terms( (int) $post_id, (array) $terms, $taxonomy, false );
				}
			}
		}

		return [
			'created'   => true,
			'dry_run'   => false,
			'post_id'   => (int) $post_id,
			'permalink' => (string) get_permalink( (int) $post_id ),
		];
	}
}
