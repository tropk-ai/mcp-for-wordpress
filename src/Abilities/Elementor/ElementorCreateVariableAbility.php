<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorRuntime;
final class ElementorCreateVariableAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-create-variable'; }
	protected function meta(): array { return [
		'label'       => __( 'Create an Elementor V4 design-system variable', 'mcp-for-wordpress' ),
		'description' => __( 'Creates a V4 design-system variable on the active Kit. type must be one of "global-color-variable" (value: hex/rgb(a)/hsl(a)) or "global-font-variable" (value: CSS font-family). Labels are case-insensitive unique per type.', 'mcp-for-wordpress' ),
		'destructive' => true,
		'idempotent'  => false,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'type', 'label', 'value' ],
		'properties'           => [
			'type'  => [ 'type' => 'string', 'enum' => [ 'global-color-variable', 'global-font-variable' ] ],
			'label' => [ 'type' => 'string', 'minLength' => 1 ],
			'value' => [ 'type' => 'string', 'minLength' => 1 ],
			'order' => [ 'type' => 'integer' ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'variable'  => [ 'type' => 'object' ],
			'watermark' => [ 'type' => [ 'integer', 'null' ] ],
		],
	]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_theme_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$repo = ElementorRuntime::require_variables();
		$args = [
			'type'  => (string) $input['type'],
			'label' => (string) $input['label'],
			'value' => (string) $input['value'],
		];
		if ( isset( $input['order'] ) ) {
			$args['order'] = (int) $input['order'];
		}
		try {
			$res = $repo->create( $args );
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( 'Create variable failed: ' . $e->getMessage(), 0, $e );
		}
		return [
			'variable'  => (array) ( $res['variable'] ?? [] ),
			'watermark' => isset( $res['watermark'] ) ? (int) $res['watermark'] : null,
		];
	}
}
