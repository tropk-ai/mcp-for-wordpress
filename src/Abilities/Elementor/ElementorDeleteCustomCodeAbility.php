<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorDeleteCustomCodeAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-delete-custom-code'; }
	protected function meta(): array { return [
		'label'       => __( 'Delete Elementor Custom Code snippet', 'mcp-for-wordpress' ),
		'description' => __( 'Trashes or permanently deletes an Elementor Pro Custom Code snippet.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'id' ],
		'properties'           => [
			'id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'force' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'id' => [ 'type' => 'integer' ], 'force' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['id'] ?? 0 );
		return $id > 0 && current_user_can( 'delete_post', $id ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$id    = (int) ( $input['id'] ?? 0 );
		$force = ! empty( $input['force'] );
		$post  = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'elementor_snippet' !== $post->post_type ) {
			throw new \RuntimeException( 'Snippet not found.' );
		}
		$result = $force ? wp_delete_post( $id, true ) : wp_trash_post( $id );
		if ( ! $result ) {
			throw new \RuntimeException( 'Failed to delete snippet.' );
		}
		return [ 'id' => $id, 'force' => $force ];
	}
}
