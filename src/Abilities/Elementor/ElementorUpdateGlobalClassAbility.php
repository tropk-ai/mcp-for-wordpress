<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\AtomicProps;
use Tropk\Mcp\Elementor\ElementorRuntime;
final class ElementorUpdateGlobalClassAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-update-global-class'; }
	protected function meta(): array { return [
		'label'       => __( 'Update an Elementor V4 global class', 'mcp-for-wordpress' ),
		'description' => __( 'Updates a V4 Global Class on the active Kit. Pass label and/or variants — variants completely replace the existing array. Use set-global-class-props to merge into a single variant instead.', 'mcp-for-wordpress' ),
		'destructive' => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'id' ],
		'properties'           => [
			'id'       => [ 'type' => 'string', 'minLength' => 1 ],
			'label'    => [ 'type' => 'string' ],
			'variants' => [ 'type' => 'array' ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'updated' => [ 'type' => 'boolean' ],
			'id'      => [ 'type' => 'string' ],
		],
	]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_theme_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$repo    = ElementorRuntime::require_global_classes();
		$id      = (string) $input['id'];
		$current = $repo->get( $id );
		if ( ! is_array( $current ) ) {
			throw new \RuntimeException( sprintf( 'Global class "%s" not found.', $id ) );
		}
		$label    = isset( $input['label'] )    ? (string) $input['label']                                              : (string) ( $current['label'] ?? '' );
		$variants = isset( $input['variants'] ) ? AtomicProps::normalize_value( (array) $input['variants'] )            : (array) ( $current['variants'] ?? [] );
		$item     = [
			'id'       => $id,
			'type'     => (string) ( $current['type'] ?? 'class' ),
			'label'    => $label,
			'variants' => array_values( (array) $variants ),
		];
		$touched  = [ $id => $item ];
		$changes  = [ 'added' => [], 'modified' => [ $id ], 'deleted' => [], 'order' => false ];
		$repo->apply_changes( $touched, $changes, (array) $repo->get_order() );
		return [ 'updated' => true, 'id' => $id ];
	}
}
