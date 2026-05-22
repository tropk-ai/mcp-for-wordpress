<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorEvaluateRenderContextAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-evaluate-render-context'; }
	protected function meta(): array { return [
		'label'       => __( 'Evaluate render context', 'mcp-for-wordpress' ),
		'description' => __( 'Reports the render context (post type, template, theme builder coverage hints) that an Elementor page is being rendered in.', 'mcp-for-wordpress' ),
		'readonly'    => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'findings' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
			'score'    => [ 'type' => 'number' ],
			'context'  => [ 'type' => 'object' ],
		],
	]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) );
	}
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		if ( ! ElementorPage::is_elementor_post( $id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $id ) );
		}
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post ) {
			throw new \RuntimeException( sprintf( 'Post %d not found.', $id ) );
		}
		$template = (string) get_post_meta( $id, '_wp_page_template', true );
		$tplType  = (string) get_post_meta( $id, '_elementor_template_type', true );
		$theme    = wp_get_theme();
		$context = [
			'post_type'        => $post->post_type,
			'post_status'      => $post->post_status,
			'page_template'    => '' === $template ? 'default' : $template,
			'template_type'    => $tplType,
			'theme'            => $theme->get( 'Name' ),
			'is_front_page'    => (int) get_option( 'page_on_front' ) === $id,
			'permalink'        => get_permalink( $id ),
		];
		$findings = [];
		if ( '' === $tplType && ! in_array( $post->post_type, [ 'page', 'post' ], true ) ) {
			$findings[] = [ 'level' => 'info', 'message' => sprintf( 'Custom post type "%s" without explicit Elementor template type.', $post->post_type ) ];
		}
		return [ 'findings' => $findings, 'score' => 1.0, 'context' => $context ];
	}
}
