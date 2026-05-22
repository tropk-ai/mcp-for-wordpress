<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Blocks;
use Tropk\Mcp\Abilities\AbstractAbility;
final class BlocksListTypesAbility extends AbstractAbility {
	public function slug(): string { return 'blocks-list-types'; }
	protected function meta(): array { return [ 'label' => __( 'List Gutenberg block types', 'mcp-for-wordpress' ), 'description' => __( 'Returns every registered block type with name, category, supports.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'blocks' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$reg = \WP_Block_Type_Registry::get_instance();
		$out = [];
		foreach ( $reg->get_all_registered() as $b ) {
			$out[] = [ 'name' => (string) $b->name, 'title' => (string) ( $b->title ?? '' ), 'category' => (string) ( $b->category ?? '' ) ];
		}
		return [ 'blocks' => $out ];
	}
}
