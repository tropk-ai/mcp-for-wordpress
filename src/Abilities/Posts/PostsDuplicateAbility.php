<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Posts;
use Tropk\Mcp\Abilities\AbstractAbility;
final class PostsDuplicateAbility extends AbstractAbility {
	public function slug(): string { return 'posts-duplicate'; }
	protected function meta(): array { return [ 'label' => __( 'Duplicate a post', 'mcp-for-wordpress' ), 'description' => __( 'Copies a post into a new draft with the same content, taxonomies and postmeta. Title is suffixed with "(Copy)".', 'mcp-for-wordpress' ) ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'id' ], 'properties' => [ 'id' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'duplicated' => [ 'type' => 'boolean' ], 'new_id' => [ 'type' => [ 'integer', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['id'] ?? 0 );
		$p = get_post( $id );
		if ( ! $p instanceof \WP_Post ) return false;
		$pto = get_post_type_object( $p->post_type );
		return $pto && current_user_can( (string) ( $pto->cap->create_posts ?? 'edit_posts' ) );
	}
	public function execute( array $input = [] ): array {
		$src = get_post( (int) $input['id'] );
		if ( ! $src instanceof \WP_Post ) throw new \RuntimeException( 'Post not found.' );
		$new_id = wp_insert_post( [
			'post_type' => $src->post_type,
			'post_status' => 'draft',
			'post_title' => $src->post_title . ' (Copy)',
			'post_content' => $src->post_content,
			'post_excerpt' => $src->post_excerpt,
			'post_author' => get_current_user_id(),
		], true );
		if ( is_wp_error( $new_id ) ) throw new \RuntimeException( $new_id->get_error_message() );
		foreach ( get_post_meta( $src->ID ) as $k => $vs ) {
			if ( str_starts_with( (string) $k, '_edit_' ) ) continue;
			foreach ( (array) $vs as $v ) add_post_meta( (int) $new_id, $k, maybe_unserialize( (string) $v ) );
		}
		foreach ( get_object_taxonomies( $src->post_type ) as $tx ) {
			$terms = wp_get_object_terms( $src->ID, $tx, [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $terms ) ) wp_set_object_terms( (int) $new_id, array_map( 'intval', $terms ), $tx );
		}
		return [ 'duplicated' => true, 'new_id' => (int) $new_id ];
	}
}
