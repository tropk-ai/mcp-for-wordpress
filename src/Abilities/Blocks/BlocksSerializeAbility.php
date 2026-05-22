<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Blocks;
use Tropk\Mcp\Abilities\AbstractAbility;
final class BlocksSerializeAbility extends AbstractAbility {
	public function slug(): string { return 'blocks-serialize'; }
	protected function meta(): array { return [ 'label' => __( 'Serialize Gutenberg block tree', 'mcp-for-wordpress' ), 'description' => __( 'Converts a parsed block tree back to its markup form via serialize_blocks().', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'blocks' ], 'properties' => [ 'blocks' => [ 'type' => 'array' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'content' => [ 'type' => 'string' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array { return [ 'content' => serialize_blocks( (array) $input['blocks'] ) ]; }
}
