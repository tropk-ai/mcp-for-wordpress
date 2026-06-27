<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorRuntime;
final class ElementorUpdateVariableAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-update-variable'; }
	protected function meta(): array { return [
		'label'       => __( 'Update an Elementor V4 design-system variable', 'mcp-for-wordpress' ),
		'description' => __( 'Updates a V4 design-system variable by id. label, value, order may be changed individually; type is immutable.', 'mcp-for-wordpress' ),
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
		$repo  = ElementorRuntime::require_variables();
		$id    = (string) $input['id'];
		$patch = [];
		foreach ( [ 'label', 'value', 'order' ] as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$patch[ $key ] = 'order' === $key ? (int) $input[ $key ] : (string) $input[ $key ];
			}
		}
		try {
			$res = $repo->update( $id, $patch );
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( 'Update variable failed: ' . $e->getMessage(), 0, $e );
		}
		return [
			'variable'  => (array) ( $res['variable'] ?? [] ),
			'watermark' => isset( $res['watermark'] ) ? (int) $res['watermark'] : null,
		];
	}
}
