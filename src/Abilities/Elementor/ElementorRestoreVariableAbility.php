<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorRuntime;
final class ElementorRestoreVariableAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-restore-variable'; }
	protected function meta(): array { return [
		'label'       => __( 'Restore a soft-deleted Elementor V4 design-system variable', 'mcp-for-wordpress' ),
		'description' => __( 'Restores a previously deleted V4 design-system variable by id. Optional label/value overrides let you rename it before re-activating (useful when the original label conflicts with one introduced after the delete).', 'mcp-for-wordpress' ),
		'destructive' => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'id' ],
		'properties'           => [
			'id'    => [ 'type' => 'string', 'minLength' => 1 ],
			'label' => [ 'type' => 'string' ],
			'value' => [ 'type' => 'string' ],
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
		$overrides = [];
		if ( array_key_exists( 'label', $input ) ) $overrides['label'] = (string) $input['label'];
		if ( array_key_exists( 'value', $input ) ) $overrides['value'] = (string) $input['value'];
		try {
			$res = $repo->restore( (string) $input['id'], $overrides );
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( 'Restore variable failed: ' . $e->getMessage(), 0, $e );
		}
		return [
			'variable'  => (array) ( $res['variable'] ?? [] ),
			'watermark' => isset( $res['watermark'] ) ? (int) $res['watermark'] : null,
		];
	}
}
