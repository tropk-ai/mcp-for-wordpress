<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorUpdateThemeBuilderConditionsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-update-theme-builder-conditions'; }
	protected function meta(): array { return [ 'label' => __( 'Update Elementor theme builder conditions', 'mcp-for-wordpress' ), 'description' => __( "Writes a template's display conditions to _elementor_conditions and to the elementor_pro_theme_builder_conditions option. Conditions may be arrays like ['include','general'] or pre-joined strings like 'include/general'.", 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'id', 'conditions' ], 'properties' => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ], 'type' => [ 'type' => 'string' ], 'conditions' => [ 'type' => 'array' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'conditions' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['id'];
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'elementor_library' !== $post->post_type ) throw new \RuntimeException( 'Template not found.' );
		$type = (string) ( $input['type'] ?? get_post_meta( $id, '_elementor_template_type', true ) );
		if ( '' === $type ) throw new \RuntimeException( 'Template type required.' );
		$formatted = [];
		foreach ( (array) $input['conditions'] as $c ) {
			if ( is_array( $c ) ) $formatted[] = implode( '/', array_map( 'strval', $c ) );
			elseif ( is_string( $c ) ) $formatted[] = $c;
		}
		update_post_meta( $id, '_elementor_conditions', $formatted );
		$all = get_option( 'elementor_pro_theme_builder_conditions', [] );
		if ( ! is_array( $all ) ) $all = [];
		if ( ! isset( $all[ $type ] ) || ! is_array( $all[ $type ] ) ) $all[ $type ] = [];
		$all[ $type ][ $id ] = $formatted;
		update_option( 'elementor_pro_theme_builder_conditions', $all );
		return [ 'updated' => true, 'id' => $id, 'type' => $type, 'conditions' => $formatted ];
	}
}
