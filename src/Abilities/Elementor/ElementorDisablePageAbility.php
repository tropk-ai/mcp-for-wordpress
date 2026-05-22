<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorDisablePageAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-disable-on-page'; }
	protected function meta(): array { return [ 'label' => __( 'Disable Elementor editor on a page', 'mcp-for-wordpress' ), 'description' => __( 'Removes _elementor_edit_mode so the post falls back to the classic / Gutenberg editor.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'disabled' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		delete_post_meta( (int) $input['post_id'], '_elementor_edit_mode' );
		return [ 'disabled' => true ];
	}
}
