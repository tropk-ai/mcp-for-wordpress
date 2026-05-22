<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorClonePageAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-clone-page'; }
	protected function meta(): array { return [
		'label' => __( 'Clone an Elementor page', 'mcp-for-wordpress' ),
		'description' => __( 'Duplicates an Elementor page, regenerating every element ID and copying page-level Elementor meta. Atomic widget JSON is preserved verbatim.', 'mcp-for-wordpress' ),
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'source_id', 'new_title' ],
		'properties'           => [
			'source_id' => [ 'type' => 'integer', 'minimum' => 1 ],
			'new_title' => [ 'type' => 'string', 'minLength' => 1 ],
			'status'    => [ 'type' => 'string', 'enum' => [ 'draft', 'pending', 'private' ], 'default' => 'draft' ],
			'author_id' => [ 'type' => 'integer', 'minimum' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'cloned' => [ 'type' => 'boolean' ], 'new_id' => [ 'type' => [ 'integer', 'null' ] ], 'permalink' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool {
		$src = (int) ( $input['source_id'] ?? 0 );
		if ( ! $src || ! current_user_can( 'read_post', $src ) ) return false;
		$p = get_post( $src );
		if ( ! $p instanceof \WP_Post ) return false;
		$pto = get_post_type_object( $p->post_type );
		$cap = $pto && isset( $pto->cap->create_posts ) ? (string) $pto->cap->create_posts : 'edit_posts';
		return current_user_can( $cap );
	}
	public function execute( array $input = [] ): array {
		$src = (int) $input['source_id'];
		if ( ! ElementorPage::is_elementor_post( $src ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $src ) );
		}
		$page = ElementorPage::load( $src );
		if ( $page->is_empty() ) {
			throw new \RuntimeException( 'Source page has empty _elementor_data.' );
		}
		$new_id = $page->clone_to_new_post( (string) $input['new_title'], (string) ( $input['status'] ?? 'draft' ), isset( $input['author_id'] ) ? (int) $input['author_id'] : null );
		return [ 'cloned' => true, 'new_id' => $new_id, 'permalink' => (string) get_permalink( $new_id ) ];
	}
}
