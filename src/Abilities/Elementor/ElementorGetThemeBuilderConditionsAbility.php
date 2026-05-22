<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorGetThemeBuilderConditionsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-theme-builder-conditions'; }
	protected function meta(): array { return [ 'label' => __( 'Get Elementor theme builder conditions', 'mcp-for-wordpress' ), 'description' => __( 'Reads the elementor_pro_theme_builder_conditions option. Optionally filter by template type or template id.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => [ 'type' => [ 'type' => 'string' ], 'id' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'conditions' => [ 'type' => [ 'array', 'object' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$all = get_option( 'elementor_pro_theme_builder_conditions', [] );
		if ( ! is_array( $all ) ) $all = [];
		if ( ! empty( $input['id'] ) ) {
			$id = (int) $input['id'];
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post ) throw new \RuntimeException( 'Template not found.' );
			$type = (string) ( $input['type'] ?? get_post_meta( $id, '_elementor_template_type', true ) );
			if ( '' === $type ) throw new \RuntimeException( 'Template type required to fetch conditions.' );
			return [ 'id' => $id, 'type' => $type, 'conditions' => $all[ $type ][ $id ] ?? [] ];
		}
		if ( ! empty( $input['type'] ) ) {
			return [ 'type' => (string) $input['type'], 'conditions' => $all[ (string) $input['type'] ] ?? [] ];
		}
		return [ 'conditions' => $all ];
	}
}
