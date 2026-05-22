<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorExportPageAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-export-page'; }
	protected function meta(): array { return [ 'label' => __( 'Export an Elementor page as JSON', 'mcp-for-wordpress' ), 'description' => __( "Returns the raw _elementor_data + page_settings JSON suitable for re-import elsewhere.", 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'data' => [ 'type' => 'array' ], 'page_settings' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		$page_settings = json_decode( (string) get_post_meta( $id, '_elementor_page_settings', true ), true );
		return [
			'post_id'       => $id,
			'data'          => ElementorPage::load( $id )->data(),
			'page_settings' => is_array( $page_settings ) ? $page_settings : new \stdClass(),
			'version'       => (string) get_post_meta( $id, '_elementor_version', true ),
			'template_type' => (string) get_post_meta( $id, '_elementor_template_type', true ),
		];
	}
}
