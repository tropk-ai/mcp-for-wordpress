<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorRuntime;
final class ElementorReorderGlobalClassesAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-reorder-global-classes'; }
	protected function meta(): array { return [
		'label'       => __( 'Reorder Elementor V4 global classes', 'mcp-for-wordpress' ),
		'description' => __( 'Sets the order in which Global Classes are rendered (later classes win CSS cascade ties). Pass the complete list of class ids in the desired order.', 'mcp-for-wordpress' ),
		'destructive' => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'order' ],
		'properties'           => [
			'order' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'updated' => [ 'type' => 'boolean' ],
			'order'   => [ 'type' => 'array' ],
		],
	]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_theme_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$repo  = ElementorRuntime::require_global_classes();
		$order = array_values( array_map( 'strval', (array) $input['order'] ) );
		$changes = [ 'added' => [], 'modified' => [], 'deleted' => [], 'order' => true ];
		$repo->apply_changes( [], $changes, $order );
		return [ 'updated' => true, 'order' => $order ];
	}
}
