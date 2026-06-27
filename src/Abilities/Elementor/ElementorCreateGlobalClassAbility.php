<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\AtomicProps;
use Tropk\Mcp\Elementor\ElementorRuntime;
final class ElementorCreateGlobalClassAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-create-global-class'; }
	protected function meta(): array { return [
		'label'       => __( 'Create an Elementor V4 global class', 'mcp-for-wordpress' ),
		'description' => __( 'Creates a new V4 Global Class on the active Kit. Provide id (e.g. "g-mb-x"), label and variants (each {meta:{breakpoint?,state?}, props:{<css-prop>:<typed value>}}). Variant props may be JSON-string envelopes; they are normalized to native objects.', 'mcp-for-wordpress' ),
		'destructive' => true,
		'idempotent'  => false,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'id', 'label' ],
		'properties'           => [
			'id'       => [ 'type' => 'string', 'minLength' => 1, 'pattern' => '^[A-Za-z][A-Za-z0-9_-]*$' ],
			'label'    => [ 'type' => 'string', 'minLength' => 1 ],
			'variants' => [ 'type' => 'array', 'default' => [] ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'created' => [ 'type' => 'boolean' ],
			'id'      => [ 'type' => 'string' ],
		],
	]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_theme_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$repo = ElementorRuntime::require_global_classes();
		$id    = (string) $input['id'];
		$label = (string) $input['label'];
		$variants = AtomicProps::normalize_value( (array) ( $input['variants'] ?? [] ) );

		if ( $repo->get( $id ) ) {
			throw new \RuntimeException( sprintf( 'Global class "%s" already exists. Use update-global-class instead.', $id ) );
		}
		$order = (array) $repo->get_order();
		$item  = [ 'id' => $id, 'type' => 'class', 'label' => $label, 'variants' => array_values( (array) $variants ) ];

		$touched = [ $id => $item ];
		$changes = [ 'added' => [ $id ], 'modified' => [], 'deleted' => [], 'order' => true ];
		$repo->apply_changes( $touched, $changes, array_values( array_unique( array_merge( $order, [ $id ] ) ) ) );

		return [ 'created' => true, 'id' => $id ];
	}
}
