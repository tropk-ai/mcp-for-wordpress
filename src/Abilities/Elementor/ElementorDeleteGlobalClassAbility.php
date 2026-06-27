<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorRuntime;
final class ElementorDeleteGlobalClassAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-delete-global-class'; }
	protected function meta(): array { return [
		'label'       => __( 'Delete an Elementor V4 global class', 'mcp-for-wordpress' ),
		'description' => __( 'Deletes a V4 Global Class on the active Kit. Elementor cascades the removal to elements that referenced the class (via the elementor/global_classes/cleanup action).', 'mcp-for-wordpress' ),
		'destructive' => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'id' ],
		'properties'           => [
			'id' => [ 'type' => 'string', 'minLength' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'deleted' => [ 'type' => 'boolean' ],
		],
	]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_theme_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$repo = ElementorRuntime::require_global_classes();
		$id   = (string) $input['id'];
		if ( ! $repo->get( $id ) ) {
			return [ 'deleted' => false ];
		}
		$order   = (array) $repo->get_order();
		$order   = array_values( array_filter( $order, static fn( $v ): bool => (string) $v !== $id ) );
		$changes = [ 'added' => [], 'modified' => [], 'deleted' => [ $id ], 'order' => true ];
		$repo->apply_changes( [], $changes, $order );
		return [ 'deleted' => true ];
	}
}
