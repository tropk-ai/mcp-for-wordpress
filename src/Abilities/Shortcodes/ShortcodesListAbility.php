<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Shortcodes;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ShortcodesListAbility extends AbstractAbility {
	public function slug(): string { return 'shortcodes-list'; }
	protected function meta(): array { return [ 'label' => __( 'List shortcodes', 'mcp-for-wordpress' ), 'description' => __( 'Returns every registered shortcode tag.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'shortcodes' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		global $shortcode_tags;
		return [ 'shortcodes' => array_keys( (array) $shortcode_tags ) ];
	}
}
