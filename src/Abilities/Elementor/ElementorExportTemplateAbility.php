<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorExportTemplateAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-export-template'; }
	protected function meta(): array { return [ 'label' => __( 'Export an Elementor template', 'mcp-for-wordpress' ), 'description' => __( 'Returns the elementor_library item as an import-ready JSON payload ({version, title, type, content, page_settings}).', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'id' ], 'properties' => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'export' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['id'];
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'elementor_library' !== $post->post_type ) throw new \RuntimeException( 'Template not found.' );
		$page_settings = get_post_meta( $id, '_elementor_page_settings', true );
		return [
			'id'    => $id,
			'title' => (string) $post->post_title,
			'type'  => (string) get_post_meta( $id, '_elementor_template_type', true ),
			'export' => [
				'version'       => '1.0',
				'title'         => (string) $post->post_title,
				'type'          => (string) ( get_post_meta( $id, '_elementor_template_type', true ) ?: 'page' ),
				'content'       => ElementorPage::load( $id )->data(),
				'page_settings' => is_array( $page_settings ) ? $page_settings : [],
			],
		];
	}
}
