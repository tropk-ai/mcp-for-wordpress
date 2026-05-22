<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Blocks;
use Tropk\Mcp\Abilities\AbstractAbility;
final class BlocksGetPatternsAbility extends AbstractAbility {
	public function slug(): string { return 'blocks-get-patterns'; }
	protected function meta(): array { return [ 'label' => __( 'List block patterns', 'mcp-for-wordpress' ), 'description' => __( 'Returns every registered block pattern available in the editor.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'patterns' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$registry = \WP_Block_Patterns_Registry::get_instance();
		$out = [];
		foreach ( $registry->get_all_registered() as $p ) {
			$out[] = [ 'name' => (string) ( $p['name'] ?? '' ), 'title' => (string) ( $p['title'] ?? '' ), 'description' => (string) ( $p['description'] ?? '' ), 'categories' => (array) ( $p['categories'] ?? [] ) ];
		}
		return [ 'patterns' => $out ];
	}
}
