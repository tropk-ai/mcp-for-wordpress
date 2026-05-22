<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorGetActiveKitAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-active-kit'; }
	protected function meta(): array { return [ 'label' => __( 'Get active Elementor Kit ID', 'mcp-for-wordpress' ), 'description' => __( 'Returns the ID and title of the currently-active Elementor Kit (Global Site Settings).', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'kit_id' => [ 'type' => [ 'integer', 'null' ] ], 'title' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$id = (int) get_option( 'elementor_active_kit' );
		if ( ! $id ) return [ 'kit_id' => null, 'title' => null ];
		$p = get_post( $id );
		return [ 'kit_id' => $id, 'title' => $p ? (string) $p->post_title : null ];
	}
}
