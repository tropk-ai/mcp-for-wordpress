<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Divi;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Divi\DiviPage;

final class DiviGetPageStructureAbility extends AbstractAbility {
	public function slug(): string { return 'divi-get-page-structure'; }
	protected function meta(): array { return [
		'label'       => __( 'Get Divi page structure', 'mcp-for-wordpress' ),
		'description' => __( 'Returns a lightweight nested structure (IDs, types, key attribute summary) for a Divi 5 page. Heavy attribute payloads are stripped for readability.', 'mcp-for-wordpress' ),
		'readonly'    => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [
			'post_id' => [ 'type' => 'integer', 'minimum' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'post_id'   => [ 'type' => 'integer' ],
		'title'     => [ 'type' => 'string' ],
		'status'    => [ 'type' => 'string' ],
		'structure' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) );
	}
	public function execute( array $input = [] ): array {
		$post_id = (int) $input['post_id'];
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			throw new \RuntimeException( sprintf( 'Post %d not found.', $post_id ) );
		}
		if ( ! DiviPage::is_divi_post( $post_id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not a Divi builder page.', $post_id ) );
		}
		if ( ! DiviPage::is_divi5_post( $post_id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d uses Divi 4. Only Divi 5 pages are supported.', $post_id ) );
		}
		$page = DiviPage::load( $post_id );
		return [
			'post_id'   => $post_id,
			'title'     => (string) $post->post_title,
			'status'    => (string) $post->post_status,
			'structure' => $page->structure(),
		];
	}
}
