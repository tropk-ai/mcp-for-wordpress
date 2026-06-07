<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Divi;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Divi\DiviPage;

final class DiviListModulesAbility extends AbstractAbility {
	public function slug(): string { return 'divi-list-modules'; }
	protected function meta(): array { return [
		'label'       => __( 'List Divi modules', 'mcp-for-wordpress' ),
		'description' => __( 'Returns a flat list of every leaf module on a Divi 5 page, with ID, type, depth and a text snippet. Optionally filter by module type.', 'mcp-for-wordpress' ),
		'readonly'    => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [
			'post_id'      => [ 'type' => 'integer', 'minimum' => 1 ],
			'module_types' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'count'   => [ 'type' => 'integer' ],
		'modules' => [ 'type' => 'array' ],
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
		$modules = DiviPage::load( $post_id )->modules();
		if ( isset( $input['module_types'] ) && is_array( $input['module_types'] ) ) {
			$filter  = array_map( 'strval', $input['module_types'] );
			$modules = array_values(
				array_filter( $modules, static fn( $m ) => in_array( $m['type'], $filter, true ) )
			);
		}
		return [
			'count'   => count( $modules ),
			'modules' => $modules,
		];
	}
}
