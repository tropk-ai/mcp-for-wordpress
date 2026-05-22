<?php
/**
 * Taxonomy term abilities for the Abilities API.
 *
 * Registers 4 abilities under `terms/*` covering generic term listing
 * and CRUD across any taxonomy. The vendored `content/*` namespace
 * handles category/tag conveniences; this is the generic equivalent.
 *
 * @package Tropk\Mcp\Extras
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_abilities_api_init',
	static function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'tropk-terms/list',
			[
				'label'               => 'Terms: list',
     'category'            => 'tropk-core',
				'description'         => 'List terms in any taxonomy with paging and optional search.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'taxonomy' ],
					'properties' => [
						'taxonomy'   => [ 'type' => 'string' ],
						'search'     => [ 'type' => 'string' ],
						'hide_empty' => [ 'type' => 'boolean', 'default' => false ],
						'parent'     => [ 'type' => 'integer' ],
						'per_page'   => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$taxonomy = (string) $input['taxonomy'];
					if ( ! taxonomy_exists( $taxonomy ) ) {
						throw new \RuntimeException( sprintf( 'Unknown taxonomy "%s".', $taxonomy ) );
					}
					$args = [
						'taxonomy'   => $taxonomy,
						'hide_empty' => (bool) ( $input['hide_empty'] ?? false ),
						'number'     => (int) ( $input['per_page'] ?? 50 ),
					];
					if ( ! empty( $input['search'] ) ) {
						$args['search'] = (string) $input['search'];
					}
					if ( isset( $input['parent'] ) ) {
						$args['parent'] = (int) $input['parent'];
					}
					$terms = get_terms( $args );
					if ( is_wp_error( $terms ) ) {
						throw new \RuntimeException( $terms->get_error_message() );
					}
					$out = [];
					foreach ( $terms as $t ) {
						$out[] = [
							'id'          => $t->term_id,
							'name'        => $t->name,
							'slug'        => $t->slug,
							'parent'      => $t->parent,
							'count'       => $t->count,
							'taxonomy'    => $t->taxonomy,
							'description' => $t->description,
						];
					}
					return [ 'terms' => $out, 'count' => count( $out ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-terms/create',
			[
				'label'               => 'Terms: create',
     'category'            => 'tropk-core',
				'description'         => 'Create a new term in any taxonomy.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'taxonomy', 'name' ],
					'properties' => [
						'taxonomy'    => [ 'type' => 'string' ],
						'name'        => [ 'type' => 'string', 'minLength' => 1 ],
						'slug'        => [ 'type' => 'string' ],
						'parent'      => [ 'type' => 'integer', 'minimum' => 0 ],
						'description' => [ 'type' => 'string' ],
						'meta'        => [ 'type' => 'object', 'additionalProperties' => true ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$taxonomy = (string) $input['taxonomy'];
					if ( ! taxonomy_exists( $taxonomy ) ) {
						throw new \RuntimeException( sprintf( 'Unknown taxonomy "%s".', $taxonomy ) );
					}
					$args = [];
					if ( ! empty( $input['slug'] ) ) {
						$args['slug'] = sanitize_title( (string) $input['slug'] );
					}
					if ( isset( $input['parent'] ) ) {
						$args['parent'] = (int) $input['parent'];
					}
					if ( isset( $input['description'] ) ) {
						$args['description'] = (string) $input['description'];
					}
					$result = wp_insert_term( (string) $input['name'], $taxonomy, $args );
					if ( is_wp_error( $result ) ) {
						throw new \RuntimeException( $result->get_error_message() );
					}
					if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
						foreach ( $input['meta'] as $k => $v ) {
							if ( is_string( $k ) && '' !== $k ) {
								update_term_meta( (int) $result['term_id'], $k, $v );
							}
						}
					}
					return [ 'created' => true, 'term_id' => (int) $result['term_id'], 'taxonomy' => $taxonomy ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_categories' ) || current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => false ] ],
			]
		);

		wp_register_ability(
			'tropk-terms/update',
			[
				'label'               => 'Terms: update',
     'category'            => 'tropk-core',
				'description'         => 'Update a term.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'taxonomy', 'term_id' ],
					'properties' => [
						'taxonomy'    => [ 'type' => 'string' ],
						'term_id'     => [ 'type' => 'integer', 'minimum' => 1 ],
						'name'        => [ 'type' => 'string' ],
						'slug'        => [ 'type' => 'string' ],
						'parent'      => [ 'type' => 'integer', 'minimum' => 0 ],
						'description' => [ 'type' => 'string' ],
						'meta'        => [ 'type' => 'object', 'additionalProperties' => true ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$taxonomy = (string) $input['taxonomy'];
					if ( ! taxonomy_exists( $taxonomy ) ) {
						throw new \RuntimeException( sprintf( 'Unknown taxonomy "%s".', $taxonomy ) );
					}
					$args = [];
					foreach ( [ 'name', 'slug', 'description' ] as $k ) {
						if ( array_key_exists( $k, $input ) ) {
							$args[ $k ] = (string) $input[ $k ];
						}
					}
					if ( isset( $input['parent'] ) ) {
						$args['parent'] = (int) $input['parent'];
					}
					$result = wp_update_term( (int) $input['term_id'], $taxonomy, $args );
					if ( is_wp_error( $result ) ) {
						throw new \RuntimeException( $result->get_error_message() );
					}
					if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
						foreach ( $input['meta'] as $k => $v ) {
							if ( is_string( $k ) && '' !== $k ) {
								update_term_meta( (int) $input['term_id'], $k, $v );
							}
						}
					}
					return [ 'updated' => true, 'term_id' => (int) $input['term_id'] ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_categories' ) || current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-terms/delete',
			[
				'label'               => 'Terms: delete',
     'category'            => 'tropk-core',
				'description'         => 'Delete a term from a taxonomy.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'taxonomy', 'term_id' ],
					'properties' => [
						'taxonomy' => [ 'type' => 'string' ],
						'term_id'  => [ 'type' => 'integer', 'minimum' => 1 ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$taxonomy = (string) $input['taxonomy'];
					if ( ! taxonomy_exists( $taxonomy ) ) {
						throw new \RuntimeException( sprintf( 'Unknown taxonomy "%s".', $taxonomy ) );
					}
					$result = wp_delete_term( (int) $input['term_id'], $taxonomy );
					if ( is_wp_error( $result ) ) {
						throw new \RuntimeException( $result->get_error_message() );
					}
					return [ 'deleted' => (bool) $result ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_categories' ) || current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => true, 'idempotent' => true ] ],
			]
		);
	},
	20
);
