<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorSaveAsTemplateAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-save-as-template'; }
	protected function meta(): array { return [ 'label' => __( 'Save an Elementor page as a template', 'mcp-for-wordpress' ), 'description' => __( 'Creates a new elementor_library entry containing the source page data (IDs regenerated).', 'mcp-for-wordpress' ) ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'source_id', 'title' ], 'properties' => [ 'source_id' => [ 'type' => 'integer' ], 'title' => [ 'type' => 'string' ], 'template_type' => [ 'type' => 'string', 'default' => 'page' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'saved' => [ 'type' => 'boolean' ], 'template_id' => [ 'type' => [ 'integer', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$src = (int) $input['source_id'];
		if ( ! ElementorPage::is_elementor_post( $src ) ) throw new \RuntimeException( 'Source is not an Elementor page.' );
		$data = ElementorPage::load( $src )->data();
		$regen = function( array &$nodes ) use ( &$regen ) {
			foreach ( $nodes as &$n ) {
				if ( ! is_array( $n ) ) continue;
				$n['id'] = bin2hex( random_bytes( 4 ) );
				if ( isset( $n['elements'] ) && is_array( $n['elements'] ) ) $regen( $n['elements'] );
			}
			unset( $n );
		};
		$regen( $data );
		$new = wp_insert_post( [ 'post_type' => 'elementor_library', 'post_title' => (string) $input['title'], 'post_status' => 'publish' ], true );
		if ( is_wp_error( $new ) ) throw new \RuntimeException( $new->get_error_message() );
		update_post_meta( (int) $new, '_elementor_data', wp_slash( (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
		update_post_meta( (int) $new, '_elementor_template_type', (string) ( $input['template_type'] ?? 'page' ) );
		update_post_meta( (int) $new, '_elementor_edit_mode', 'builder' );
		return [ 'saved' => true, 'template_id' => (int) $new ];
	}
}
