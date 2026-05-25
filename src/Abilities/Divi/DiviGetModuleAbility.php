<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Divi;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Divi\DiviPage;

final class DiviGetModuleAbility extends AbstractAbility {
	public function slug(): string { return 'divi-get-module'; }
	protected function meta(): array { return [
		'label'       => __( 'Get a Divi module', 'mcp-for-wordpress' ),
		'description' => __( 'Returns the full parsed node (type, id, attrs, children, inner content) for a single Divi 5 module by its ID.', 'mcp-for-wordpress' ),
		'readonly'    => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'module_id' ],
		'properties'           => [
			'post_id'   => [ 'type' => 'integer', 'minimum' => 1 ],
			'module_id' => [ 'type' => 'string', 'minLength' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'found' => [ 'type' => 'boolean' ],
		'node'  => [ 'type' => [ 'object', 'null' ] ],
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
		$node = DiviPage::load( $post_id )->find_module( (string) $input['module_id'] );
		return [
			'found' => null !== $node,
			'node'  => $node,
		];
	}
}
