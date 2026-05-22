<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorClearCacheAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-clear-cache'; }
	protected function meta(): array { return [
		'label'       => __( 'Clear Elementor cache', 'mcp-for-wordpress' ),
		'description' => __( 'Clears Elementor cache. scope="post" requires id and clears post-level CSS meta; scope="site" clears Elementor files_manager cache globally.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [
			'id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'scope' => [ 'type' => 'string', 'enum' => [ 'post', 'site' ], 'default' => 'post' ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'scope' => [ 'type' => 'string' ], 'cleared' => [ 'type' => 'boolean' ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_posts' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$scope = (string) ( $input['scope'] ?? 'post' );
		if ( 'site' === $scope ) {
			$cleared = false;
			if ( class_exists( '\\Elementor\\Plugin' ) ) {
				$el = \Elementor\Plugin::$instance ?? null;
				if ( $el && isset( $el->files_manager ) && method_exists( $el->files_manager, 'clear_cache' ) ) {
					$el->files_manager->clear_cache();
					$cleared = true;
				}
			}
			delete_option( '_elementor_global_css' );
			return [ 'scope' => 'site', 'cleared' => $cleared ];
		}
		$id = (int) ( $input['id'] ?? 0 );
		if ( $id <= 0 || ! current_user_can( 'edit_post', $id ) ) {
			throw new \RuntimeException( 'A valid id is required for scope="post".' );
		}
		delete_post_meta( $id, '_elementor_css' );
		return [ 'scope' => 'post', 'cleared' => true ];
	}
}
