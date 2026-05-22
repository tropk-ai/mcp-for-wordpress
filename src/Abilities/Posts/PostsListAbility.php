<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Posts;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;

final class PostsListAbility implements Ability {

	private const DEFAULT_FIELDS = [ 'id', 'title', 'status', 'date', 'modified', 'permalink', 'post_type', 'author_id' ];
	private const ALLOWED_FIELDS = [ 'id', 'title', 'status', 'date', 'modified', 'permalink', 'post_type', 'author_id', 'excerpt', 'slug', 'parent', 'menu_order' ];

	public function slug(): string {
		return 'posts-list';
	}

	public function definition(): array {
		return [
			'label'               => __( 'List posts', 'mcp-for-wordpress' ),
			'description'         => __( 'Lists posts / pages / CPTs with pagination, search, and field projection. Read-only.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => [
					'post_type'    => [ 'type' => 'string', 'default' => 'post' ],
					'post_status'  => [
						'type'    => 'array',
						'items'   => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'any' ] ],
						'default' => [ 'publish' ],
					],
					'search'  => [ 'type' => 'string' ],
					'author'  => [ 'type' => 'integer', 'minimum' => 0 ],
					'limit'   => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
					'offset'  => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0 ],
					'orderby' => [ 'type' => 'string', 'enum' => [ 'date', 'modified', 'title', 'menu_order', 'ID' ], 'default' => 'date' ],
					'order'   => [ 'type' => 'string', 'enum' => [ 'ASC', 'DESC' ], 'default' => 'DESC' ],
					'fields'  => [
						'type'  => 'array',
						'items' => [ 'type' => 'string', 'enum' => self::ALLOWED_FIELDS ],
					],
				],
			],
			'output_schema'       => [
				'$schema'    => 'https://json-schema.org/draft/2020-12/schema',
				'type'       => 'object',
				'properties' => [
					'items'    => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
					'pageInfo' => [ 'type' => 'object' ],
				],
				'required'   => [ 'items', 'pageInfo' ],
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
		$type_object = get_post_type_object( (string) ( $input['post_type'] ?? 'post' ) );
		$read_cap    = $type_object && isset( $type_object->cap->read ) ? (string) $type_object->cap->read : 'read';
		return current_user_can( $read_cap );
	}

	public function execute( array $input = [] ): array {
		$post_type   = (string) ( $input['post_type'] ?? 'post' );
		$post_status = (array) ( $input['post_status'] ?? [ 'publish' ] );
		$limit       = (int) ( $input['limit'] ?? 20 );
		$offset      = (int) ( $input['offset'] ?? 0 );
		$fields      = array_values( array_intersect( (array) ( $input['fields'] ?? self::DEFAULT_FIELDS ), self::ALLOWED_FIELDS ) );
		if ( [] === $fields ) {
			$fields = self::DEFAULT_FIELDS;
		}

		$args = [
			'post_type'        => $post_type,
			'post_status'      => $post_status,
			'posts_per_page'   => $limit,
			'offset'           => $offset,
			'orderby'          => (string) ( $input['orderby'] ?? 'date' ),
			'order'            => (string) ( $input['order'] ?? 'DESC' ),
			'suppress_filters' => false,
			'no_found_rows'    => false,
		];
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = (string) $input['search'];
		}
		if ( ! empty( $input['author'] ) ) {
			$args['author'] = (int) $input['author'];
		}

		$query = new \WP_Query( $args );
		$items = [];
		foreach ( $query->posts as $post ) {
			$items[] = $this->project( $post, $fields );
		}

		$total       = (int) $query->found_posts;
		$has_more    = ( $offset + count( $items ) ) < $total;
		$next_offset = $has_more ? $offset + $limit : null;

		return [
			'items'    => $items,
			'pageInfo' => [
				'total'      => $total,
				'limit'      => $limit,
				'offset'     => $offset,
				'hasMore'    => $has_more,
				'nextOffset' => $next_offset,
			],
		];
	}

	/**
	 * @param array<int, string> $fields
	 * @return array<string, mixed>
	 */
	private function project( \WP_Post $post, array $fields ): array {
		static $map = null;
		if ( null === $map ) {
			$map = [
				'id'         => static fn( \WP_Post $p ) => (int) $p->ID,
				'title'      => static fn( \WP_Post $p ) => (string) $p->post_title,
				'status'     => static fn( \WP_Post $p ) => (string) $p->post_status,
				'date'       => static fn( \WP_Post $p ) => (string) $p->post_date_gmt,
				'modified'   => static fn( \WP_Post $p ) => (string) $p->post_modified_gmt,
				'permalink'  => static fn( \WP_Post $p ) => (string) get_permalink( $p ),
				'post_type'  => static fn( \WP_Post $p ) => (string) $p->post_type,
				'author_id'  => static fn( \WP_Post $p ) => (int) $p->post_author,
				'excerpt'    => static fn( \WP_Post $p ) => (string) get_the_excerpt( $p ),
				'slug'       => static fn( \WP_Post $p ) => (string) $p->post_name,
				'parent'     => static fn( \WP_Post $p ) => (int) $p->post_parent,
				'menu_order' => static fn( \WP_Post $p ) => (int) $p->menu_order,
			];
		}
		$out = [];
		foreach ( $fields as $f ) {
			if ( isset( $map[ $f ] ) ) {
				$out[ $f ] = $map[ $f ]( $post );
			}
		}
		return $out;
	}
}
