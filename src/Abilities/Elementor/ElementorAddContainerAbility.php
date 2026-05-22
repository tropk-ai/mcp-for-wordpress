<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorAddContainerAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-add-container'; }
	protected function meta(): array { return [ 'label' => __( 'Append a container to an Elementor page', 'mcp-for-wordpress' ), 'description' => __( 'Appends a new top-level container with a stable ID. Use the returned container_id with elementor-add-widget afterwards.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'settings' => [ 'type' => 'object' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'added' => [ 'type' => 'boolean' ], 'container_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$post_id = (int) $input['post_id'];
		if ( ! ElementorPage::is_elementor_post( $post_id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $post_id ) );
		}
		$snap = ( new SnapshotManager() )->snapshot_post( $post_id, 'elementor-add-container' );
		$raw  = get_post_meta( $post_id, '_elementor_data', true );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : [] );
		if ( ! is_array( $data ) ) $data = [];
		$id = bin2hex( random_bytes( 4 ) );
		$data[] = [
			'id'       => $id,
			'elType'   => 'container',
			'settings' => is_array( $input['settings'] ?? null ) ? (array) $input['settings'] : new \stdClass(),
			'elements' => [],
			'isInner'  => false,
		];
		update_post_meta( $post_id, '_elementor_data', wp_slash( (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
		delete_post_meta( $post_id, '_elementor_css' );
		return [ 'added' => true, 'container_id' => $id, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
