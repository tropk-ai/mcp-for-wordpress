<?php
/**
 * Media abilities for the Abilities API.
 *
 * Registers ~5 advanced media abilities under `media-ext/*`. Basic
 * upload / get / update / delete already exist in the bjornfix
 * `media/*` namespace.
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
			'tropk-media/find-unused',
			[
				'label'               => 'Media: find unused attachments',
     'category'            => 'tropk-core',
				'description'         => 'Returns attachments that are not referenced as a featured image and do not appear in any post content. Heuristic: SQL LIKE on the file URL across {posts}.post_content.',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					global $wpdb;
					$limit  = (int) ( $input['limit'] ?? 50 );
					$query  = new \WP_Query(
						[
							'post_type'      => 'attachment',
							'post_status'    => 'inherit',
							'posts_per_page' => $limit * 4,
							'orderby'        => 'date',
							'order'          => 'ASC',
						]
					);
					$unused = [];
					foreach ( $query->posts as $att ) {
						if ( count( $unused ) >= $limit ) {
							break;
						}
						$is_featured = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s", (string) $att->ID ) );
						if ( $is_featured > 0 ) {
							continue;
						}
						$url = wp_get_attachment_url( $att->ID );
						if ( ! $url ) {
							continue;
						}
						$file_base = basename( (string) get_attached_file( $att->ID ) );
						$used      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private','future') AND ID != %d AND post_content LIKE %s", $att->ID, '%' . $wpdb->esc_like( $file_base ) . '%' ) );
						if ( 0 === $used ) {
							$unused[] = [
								'id'    => $att->ID,
								'title' => $att->post_title,
								'url'   => $url,
								'mime'  => $att->post_mime_type,
								'date'  => $att->post_date_gmt,
							];
						}
					}
					return [ 'attachments' => $unused, 'count' => count( $unused ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'upload_files' ) && current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-media/bulk-delete',
			[
				'label'               => 'Media: bulk delete attachments',
     'category'            => 'tropk-core',
				'description'         => 'Force-delete a list of attachment IDs.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'ids' ],
					'properties' => [
						'ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer', 'minimum' => 1 ] ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$deleted = [];
					foreach ( (array) $input['ids'] as $id ) {
						$ok = wp_delete_attachment( (int) $id, true );
						if ( false !== $ok ) {
							$deleted[] = (int) $id;
						}
					}
					return [ 'deleted' => $deleted, 'count' => count( $deleted ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'delete_others_posts' ),
				'meta'                => [ 'annotations' => [ 'destructive' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-media/convert-to-webp',
			[
				'label'               => 'Media: convert image to WebP',
     'category'            => 'tropk-core',
				'description'         => 'Generate a .webp sibling of an attachment\'s source file using GD or Imagick. Original file is kept; the WebP URL is returned.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'id' ],
					'properties' => [
						'id'      => [ 'type' => 'integer', 'minimum' => 1 ],
						'quality' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 82 ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$src = get_attached_file( (int) $input['id'] );
					if ( ! $src || ! file_exists( $src ) ) {
						throw new \RuntimeException( 'Source file not found.' );
					}
					if ( ! in_array( wp_check_filetype( $src )['type'] ?? '', [ 'image/jpeg', 'image/png' ], true ) ) {
						throw new \RuntimeException( 'Only JPEG and PNG inputs are supported.' );
					}
					$dest    = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $src );
					$quality = (int) ( $input['quality'] ?? 82 );
					$editor  = wp_get_image_editor( $src );
					if ( is_wp_error( $editor ) ) {
						throw new \RuntimeException( $editor->get_error_message() );
					}
					$editor->set_quality( $quality );
					$saved = $editor->save( $dest, 'image/webp' );
					if ( is_wp_error( $saved ) ) {
						throw new \RuntimeException( $saved->get_error_message() );
					}
					$upload_dir = wp_upload_dir();
					$url        = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], (string) $saved['path'] );
					return [ 'converted' => true, 'webp_path' => $saved['path'], 'webp_url' => $url, 'bytes' => filesize( $saved['path'] ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'upload_files' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-media/storage-stats',
			[
				'label'               => 'Media: storage stats',
     'category'            => 'tropk-core',
				'description'         => 'Returns counts and total disk usage of the uploads directory by mime type.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					global $wpdb;
					$rows = $wpdb->get_results( "SELECT post_mime_type AS mime, COUNT(*) AS items FROM {$wpdb->posts} WHERE post_type = 'attachment' GROUP BY post_mime_type", ARRAY_A );
					$by_type  = [];
					$total    = 0;
					foreach ( $rows as $r ) {
						$by_type[] = [ 'mime' => (string) $r['mime'], 'items' => (int) $r['items'] ];
						$total    += (int) $r['items'];
					}
					$upload   = wp_upload_dir();
					$total_bytes = 0;
					$iter        = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $upload['basedir'], \FilesystemIterator::SKIP_DOTS ) );
					foreach ( $iter as $f ) {
						if ( $f->isFile() ) {
							$total_bytes += (int) $f->getSize();
						}
					}
					return [
						'total_attachments' => $total,
						'by_mime'           => $by_type,
						'disk_bytes'        => $total_bytes,
						'disk_mb'           => round( $total_bytes / 1024 / 1024, 2 ),
						'uploads_dir'       => $upload['basedir'],
					];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-media/set-featured-image',
			[
				'label'               => 'Media: set featured image',
     'category'            => 'tropk-core',
				'description'         => 'Set the featured image (_thumbnail_id) of a post.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'post_id', 'attachment_id' ],
					'properties' => [
						'post_id'       => [ 'type' => 'integer', 'minimum' => 1 ],
						'attachment_id' => [ 'type' => 'integer', 'minimum' => 1 ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$ok = set_post_thumbnail( (int) $input['post_id'], (int) $input['attachment_id'] );
					if ( false === $ok ) {
						throw new \RuntimeException( 'Failed to set featured image.' );
					}
					return [ 'updated' => true, 'post_id' => (int) $input['post_id'], 'attachment_id' => (int) $input['attachment_id'] ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);
	},
	20
);
