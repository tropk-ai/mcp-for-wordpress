<?php
/**
 * Bulk operations for the Abilities API.
 *
 * Registers 3 abilities under `bulk/*` that fan out repeated post writes
 * inside a single tool invocation. The vendored `content/*` namespace
 * already covers single-post create/update/delete; this is the
 * "batched" complement.
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
			'tropk-bulk/create-posts',
			[
				'label'               => 'Bulk: create posts',
     'category'            => 'tropk-core',
				'description'         => 'Create up to 100 posts in one call. Returns the IDs of created posts and the index/error of failures.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'posts' ],
					'properties' => [
						'posts' => [
							'type'     => 'array',
							'minItems' => 1,
							'maxItems' => 100,
							'items'    => [
								'type'       => 'object',
								'required'   => [ 'post_type', 'title' ],
								'properties' => [
									'post_type' => [ 'type' => 'string', 'default' => 'post' ],
									'title'     => [ 'type' => 'string' ],
									'content'   => [ 'type' => 'string' ],
									'excerpt'   => [ 'type' => 'string' ],
									'status'    => [ 'type' => 'string', 'enum' => [ 'draft', 'pending', 'private', 'publish', 'future' ], 'default' => 'draft' ],
									'slug'      => [ 'type' => 'string' ],
									'date'      => [ 'type' => 'string' ],
									'meta'      => [ 'type' => 'object', 'additionalProperties' => true ],
								],
							],
						],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$created = [];
					$errors  = [];
					foreach ( (array) $input['posts'] as $i => $p ) {
						$args = [
							'post_type'    => (string) ( $p['post_type'] ?? 'post' ),
							'post_title'   => (string) ( $p['title'] ?? '' ),
							'post_content' => (string) ( $p['content'] ?? '' ),
							'post_excerpt' => (string) ( $p['excerpt'] ?? '' ),
							'post_status'  => (string) ( $p['status'] ?? 'draft' ),
						];
						if ( isset( $p['slug'] ) ) {
							$args['post_name'] = sanitize_title( (string) $p['slug'] );
						}
						if ( isset( $p['date'] ) ) {
							$args['post_date_gmt'] = (string) $p['date'];
						}
						$id = wp_insert_post( wp_slash( $args ), true );
						if ( is_wp_error( $id ) ) {
							$errors[] = [ 'index' => $i, 'error' => $id->get_error_message() ];
							continue;
						}
						if ( isset( $p['meta'] ) && is_array( $p['meta'] ) ) {
							foreach ( $p['meta'] as $k => $v ) {
								if ( is_string( $k ) && '' !== $k ) {
									update_post_meta( (int) $id, $k, $v );
								}
							}
						}
						$created[] = (int) $id;
					}
					return [ 'created' => $created, 'count' => count( $created ), 'errors' => $errors ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => false ] ],
			]
		);

		wp_register_ability(
			'tropk-bulk/update-posts',
			[
				'label'               => 'Bulk: update posts',
     'category'            => 'tropk-core',
				'description'         => 'Update up to 100 posts in one call. Each item must include `id` plus the fields to change.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'updates' ],
					'properties' => [
						'updates' => [
							'type'     => 'array',
							'minItems' => 1,
							'maxItems' => 100,
							'items'    => [
								'type'       => 'object',
								'required'   => [ 'id' ],
								'properties' => [
									'id'      => [ 'type' => 'integer', 'minimum' => 1 ],
									'title'   => [ 'type' => 'string' ],
									'content' => [ 'type' => 'string' ],
									'excerpt' => [ 'type' => 'string' ],
									'status'  => [ 'type' => 'string' ],
									'meta'    => [ 'type' => 'object', 'additionalProperties' => true ],
								],
							],
						],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$ok = [];
					$errors = [];
					foreach ( (array) $input['updates'] as $i => $u ) {
						$args = [ 'ID' => (int) $u['id'] ];
						if ( isset( $u['title'] ) ) {
							$args['post_title'] = (string) $u['title'];
						}
						if ( isset( $u['content'] ) ) {
							$args['post_content'] = (string) $u['content'];
						}
						if ( isset( $u['excerpt'] ) ) {
							$args['post_excerpt'] = (string) $u['excerpt'];
						}
						if ( isset( $u['status'] ) ) {
							$args['post_status'] = (string) $u['status'];
						}
						$result = wp_update_post( wp_slash( $args ), true );
						if ( is_wp_error( $result ) ) {
							$errors[] = [ 'index' => $i, 'id' => (int) $u['id'], 'error' => $result->get_error_message() ];
							continue;
						}
						if ( isset( $u['meta'] ) && is_array( $u['meta'] ) ) {
							foreach ( $u['meta'] as $k => $v ) {
								if ( is_string( $k ) && '' !== $k ) {
									update_post_meta( (int) $u['id'], $k, $v );
								}
							}
						}
						$ok[] = (int) $u['id'];
					}
					return [ 'updated' => $ok, 'count' => count( $ok ), 'errors' => $errors ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-bulk/delete-posts',
			[
				'label'               => 'Bulk: delete posts',
     'category'            => 'tropk-core',
				'description'         => 'Delete (trash by default; force=true to force-delete) up to 100 posts.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'ids' ],
					'properties' => [
						'ids'   => [ 'type' => 'array', 'minItems' => 1, 'maxItems' => 100, 'items' => [ 'type' => 'integer', 'minimum' => 1 ] ],
						'force' => [ 'type' => 'boolean', 'default' => false ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$force   = (bool) ( $input['force'] ?? false );
					$deleted = [];
					foreach ( (array) $input['ids'] as $id ) {
						$result = wp_delete_post( (int) $id, $force );
						if ( $result ) {
							$deleted[] = (int) $id;
						}
					}
					return [ 'deleted' => $deleted, 'count' => count( $deleted ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'delete_posts' ),
				'meta'                => [ 'annotations' => [ 'destructive' => true, 'idempotent' => true ] ],
			]
		);
	},
	20
);
