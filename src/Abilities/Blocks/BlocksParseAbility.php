<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Blocks;
use Tropk\Mcp\Abilities\AbstractAbility;
final class BlocksParseAbility extends AbstractAbility {
	public function slug(): string { return 'blocks-parse'; }
	protected function meta(): array { return [ 'label' => __( 'Parse Gutenberg block markup', 'mcp-for-wordpress' ), 'description' => __( 'Runs parse_blocks() on the supplied content and returns the parsed tree.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'content' ], 'properties' => [ 'content' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'blocks' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array { return [ 'blocks' => parse_blocks( (string) $input['content'] ) ]; }
}
