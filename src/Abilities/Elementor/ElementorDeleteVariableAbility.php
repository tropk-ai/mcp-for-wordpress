<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorRuntime;
final class ElementorDeleteVariableAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-delete-variable'; }
	protected function meta(): array { return [
		'label'       => __( 'Delete an Elementor V4 design-system variable', 'mcp-for-wordpress' ),
		'description' => __( 'Soft-deletes a V4 design-system variable by id (marks deleted=true, sets deleted_at). Use elementor-restore-variable to undo.', 'mcp-for-wordpress' ),
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
			'variable'  => [ 'type' => 'object' ],
			'watermark' => [ 'type' => [ 'integer', 'null' ] ],
		],
	]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_theme_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$repo = ElementorRuntime::require_variables();
		try {
			$res = $repo->delete( (string) $input['id'] );
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( 'Delete variable failed: ' . $e->getMessage(), 0, $e );
		}
		return [
			'variable'  => (array) ( $res['variable'] ?? [] ),
			'watermark' => isset( $res['watermark'] ) ? (int) $res['watermark'] : null,
		];
	}
}
