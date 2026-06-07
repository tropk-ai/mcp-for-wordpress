<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Divi;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Divi\DiviPage;

final class DiviGetPageOutlineAbility extends AbstractAbility {
	public function slug(): string { return 'divi-get-page-outline'; }
	protected function meta(): array { return [
		'label'       => __( 'Get Divi page outline', 'mcp-for-wordpress' ),
		'description' => __( 'Returns a compact indented text outline of a Divi 5 page, showing the section/row/column/module hierarchy with IDs and a text snippet for each module.', 'mcp-for-wordpress' ),
		'readonly'    => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [
			'post_id'   => [ 'type' => 'integer', 'minimum' => 1 ],
			'max_bytes' => [ 'type' => 'integer', 'minimum' => 256, 'maximum' => 16384, 'default' => 2048 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'post_id'  => [ 'type' => 'integer' ],
		'is_empty' => [ 'type' => 'boolean' ],
		'outline'  => [ 'type' => 'string' ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) );
	}
	public function execute( array $input = [] ): array {
		$post_id = (int) $input['post_id'];
		if ( ! DiviPage::is_divi_post( $post_id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not a Divi builder page.', $post_id ) );
		}
		if ( ! DiviPage::is_divi5_post( $post_id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d uses Divi 4. Only Divi 5 pages are supported.', $post_id ) );
		}
		$page = DiviPage::load( $post_id );
		return [
			'post_id'  => $page->post_id(),
			'is_empty' => $page->is_empty(),
			'outline'  => $page->outline( (int) ( $input['max_bytes'] ?? 2048 ) ),
		];
	}
}
