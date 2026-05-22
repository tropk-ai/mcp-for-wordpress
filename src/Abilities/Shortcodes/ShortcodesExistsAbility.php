<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Shortcodes;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ShortcodesExistsAbility extends AbstractAbility {
	public function slug(): string { return 'shortcodes-exists'; }
	protected function meta(): array { return [ 'label' => __( 'Check whether a shortcode is registered', 'mcp-for-wordpress' ), 'description' => __( 'Returns true/false for a given shortcode tag.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'tag' ], 'properties' => [ 'tag' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'exists' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array { return [ 'exists' => shortcode_exists( (string) $input['tag'] ) ]; }
}
