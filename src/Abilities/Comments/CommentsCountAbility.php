<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Comments;
use Tropk\Mcp\Abilities\AbstractAbility;
final class CommentsCountAbility extends AbstractAbility {
	public function slug(): string { return 'comments-count'; }
	protected function meta(): array { return [ 'label' => __( 'Count comments by status', 'mcp-for-wordpress' ), 'description' => __( 'Returns counts for approved, awaiting moderation, spam and trash.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 0 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'counts' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'moderate_comments' ); }
	public function execute( array $input = [] ): array {
		$c = wp_count_comments( (int) ( $input['post_id'] ?? 0 ) );
		return [ 'counts' => [
			'approved' => (int) ( $c->approved ?? 0 ),
			'moderated' => (int) ( $c->moderated ?? 0 ),
			'spam' => (int) ( $c->spam ?? 0 ),
			'trash' => (int) ( $c->trash ?? 0 ),
			'total_comments' => (int) ( $c->total_comments ?? 0 ),
		] ];
	}
}
