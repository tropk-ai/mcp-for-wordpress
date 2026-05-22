<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Posts;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Backup\SnapshotManager;

final class PostsUpdateAbility implements Ability {

	public function slug(): string {
		return 'posts-update';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Update a post', 'mcp-for-wordpress' ),
			'description'         => __( 'Patches an existing post. Always snapshots the post before writing. Supports dry_run for diff-only.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'id', 'patch' ],
				'properties'           => [
					'id'      => [ 'type' => 'integer', 'minimum' => 1 ],
					'patch'   => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => [
							'title'      => [ 'type' => 'string' ],
							'content'    => [ 'type' => 'string' ],
							'excerpt'    => [ 'type' => 'string' ],
							'status'     => [ 'type' => 'string', 'enum' => [ 'draft', 'pending', 'private', 'publish', 'future', 'trash' ] ],
							'slug'       => [ 'type' => 'string' ],
							'author_id'  => [ 'type' => 'integer', 'minimum' => 1 ],
							'parent_id'  => [ 'type' => 'integer', 'minimum' => 0 ],
							'menu_order' => [ 'type' => 'integer' ],
							'date'       => [ 'type' => 'string' ],
						],
					],
					'dry_run' => [ 'type' => 'boolean', 'default' => false ],
				],
			],
			'output_schema'       => [
				'$schema'    => 'https://json-schema.org/draft/2020-12/schema',
				'type'       => 'object',
				'properties' => [
					'updated'     => [ 'type' => 'boolean' ],
					'dry_run'     => [ 'type' => 'boolean' ],
					'post_id'     => [ 'type' => 'integer' ],
					'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
					'diff'        => [ 'type' => 'object' ],
				],
			],
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations'  => [ 'readonly' => false, 'destructive' => true, 'idempotent' => false ],
				'show_in_rest' => true,
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['id'] ?? 0 );
		if ( $id <= 0 ) {
			return false;
		}
		return current_user_can( 'edit_post', $id ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}

	public function execute( array $input ): array {
		$id      = (int) $input['id'];
		$patch   = (array) ( $input['patch'] ?? [] );
		$dry_run = (bool) ( $input['dry_run'] ?? false );
		$post    = get_post( $id );
		if ( ! $post instanceof \WP_Post ) {
			throw new \RuntimeException( sprintf( 'Post %d not found.', $id ) );
		}

		$map = [
			'title'      => [ 'post_title',     static fn( $v ) => (string) $v ],
			'content'    => [ 'post_content',   static fn( $v ) => (string) $v ],
			'excerpt'    => [ 'post_excerpt',   static fn( $v ) => (string) $v ],
			'status'     => [ 'post_status',    static fn( $v ) => (string) $v ],
			'slug'       => [ 'post_name',      static fn( $v ) => sanitize_title( (string) $v ) ],
			'author_id'  => [ 'post_author',    static fn( $v ) => (int) $v ],
			'parent_id'  => [ 'post_parent',    static fn( $v ) => (int) $v ],
			'menu_order' => [ 'menu_order',     static fn( $v ) => (int) $v ],
			'date'       => [ 'post_date_gmt',  static fn( $v ) => (string) $v ],
		];
		$diff   = [];
		$update = [ 'ID' => $id ];
		foreach ( $patch as $key => $value ) {
			if ( ! isset( $map[ $key ] ) ) {
				continue;
			}
			[ $field, $caster ] = $map[ $key ];
			$new = $caster( $value );
			if ( (string) $post->$field !== (string) $new ) {
				$diff[ $key ]   = [ 'from' => $post->$field, 'to' => $new ];
				$update[ $field ] = $new;
			}
		}

		if ( $dry_run || [] === $diff ) {
			return [ 'updated' => false, 'dry_run' => $dry_run, 'post_id' => $id, 'snapshot_id' => null, 'diff' => $diff ];
		}

		$snapshot    = ( new SnapshotManager() )->snapshot_post( $id, 'posts-update' );
		$snapshot_id = $snapshot['snapshot_id'];

		$res = wp_update_post( wp_slash( $update ), true );
		if ( is_wp_error( $res ) ) {
			throw new \RuntimeException( 'wp_update_post failed: ' . $res->get_error_message() );
		}

		return [ 'updated' => true, 'dry_run' => false, 'post_id' => $id, 'snapshot_id' => $snapshot_id, 'diff' => $diff ];
	}
}
