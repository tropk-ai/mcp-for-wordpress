<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Content;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ContentSchedulePostAbility extends AbstractAbility {
	public function slug(): string { return 'content-schedule-post'; }
	protected function meta(): array { return [ 'label' => __( 'Schedule a post for future publication', 'mcp-for-wordpress' ), 'description' => __( 'Switches a post to status=future and sets its publish date.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'id', 'datetime_gmt' ], 'properties' => [ 'id' => [ 'type' => 'integer' ], 'datetime_gmt' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'scheduled' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'publish_posts' ) && current_user_can( 'edit_post', (int) ( $input['id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$res = wp_update_post( [ 'ID' => (int) $input['id'], 'post_status' => 'future', 'post_date_gmt' => (string) $input['datetime_gmt'] ], true );
		if ( is_wp_error( $res ) ) throw new \RuntimeException( $res->get_error_message() );
		return [ 'scheduled' => true ];
	}
}
