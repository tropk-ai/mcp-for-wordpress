<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Shortcodes;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ShortcodesRenderAbility extends AbstractAbility {
	public function slug(): string { return 'shortcodes-render'; }
	protected function meta(): array { return [ 'label' => __( 'Render a shortcode', 'mcp-for-wordpress' ), 'description' => __( 'Runs do_shortcode on an arbitrary shortcode string and returns the HTML.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'shortcode' ], 'properties' => [ 'shortcode' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'html' => [ 'type' => 'string' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		return [ 'html' => do_shortcode( (string) $input['shortcode'] ) ];
	}
}
