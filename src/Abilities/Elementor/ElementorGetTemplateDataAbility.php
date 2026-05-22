<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorGetTemplateDataAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-template-data'; }
	protected function meta(): array { return [ 'label' => __( "Get an Elementor template's JSON", 'mcp-for-wordpress' ), 'description' => __( 'Returns the raw _elementor_data of a saved template. Use this to inspect a section/page template before applying it.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'template_id' ], 'properties' => [ 'template_id' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'data' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['template_id'];
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'elementor_library' !== $post->post_type ) {
			throw new \RuntimeException( 'Template not found.' );
		}
		return [ 'data' => ElementorPage::load( $id )->data(), 'type' => (string) get_post_meta( $id, '_elementor_template_type', true ) ];
	}
}
