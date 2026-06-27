<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorRuntime;
final class ElementorGetGlobalClassAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-global-class'; }
	protected function meta(): array { return [
		'label'       => __( 'Get a single Elementor V4 global class', 'mcp-for-wordpress' ),
		'description' => __( 'Returns one Global Class by id, with its label, type and the variants array (breakpoint/state + CSS-prop map).', 'mcp-for-wordpress' ),
		'readonly'    => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'class_id' ],
		'properties'           => [
			'class_id' => [ 'type' => 'string', 'minLength' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'class' => [ 'type' => [ 'object', 'null' ] ],
		],
	]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$repo = ElementorRuntime::require_global_classes();
		$class = $repo->get( (string) $input['class_id'] );
		return [ 'class' => is_array( $class ) ? $class : null ];
	}
}
