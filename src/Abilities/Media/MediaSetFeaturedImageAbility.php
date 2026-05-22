<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Media;
use Tropk\Mcp\Abilities\AbstractAbility;
final class MediaSetFeaturedImageAbility extends AbstractAbility {
	public function slug(): string { return 'media-set-featured-image'; }
	protected function meta(): array { return [ 'label' => __( 'Set a featured image', 'mcp-for-wordpress' ), 'description' => __( 'Sets the featured image (post thumbnail) of a post.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id', 'attachment_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'attachment_id' => [ 'type' => 'integer' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'set' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array { return [ 'set' => (bool) set_post_thumbnail( (int) $input['post_id'], (int) $input['attachment_id'] ) ]; }
}
