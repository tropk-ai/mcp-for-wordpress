<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\RankMath;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
final class RankMathUpdateMetaAbility extends AbstractAbility {
	private const MAP = [ 'title' => 'rank_math_title', 'description' => 'rank_math_description', 'focus_keyword' => 'rank_math_focus_keyword', 'canonical' => 'rank_math_canonical_url', 'robots' => 'rank_math_robots', 'breadcrumb' => 'rank_math_breadcrumb_title', 'pillar' => 'rank_math_pillar_content', 'fb_title' => 'rank_math_facebook_title', 'fb_description' => 'rank_math_facebook_description', 'twitter_title' => 'rank_math_twitter_title', 'twitter_description' => 'rank_math_twitter_description' ];
	public function slug(): string { return 'rankmath-update-meta'; }
	protected function meta(): array { return [ 'label' => __( 'Update Rank Math meta', 'mcp-for-wordpress' ), 'description' => __( 'Patches Rank Math SEO meta. Pass null to delete a field. Snapshots the post first.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id', 'fields' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ], 'fields' => [ 'type' => 'object' ], 'dry_run' => [ 'type' => 'boolean' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'array' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		if ( ! empty( $input['dry_run'] ) ) return [ 'updated' => array_keys( (array) $input['fields'] ), 'snapshot_id' => null ];
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'rankmath-update-meta' );
		$done = [];
		foreach ( (array) $input['fields'] as $alias => $value ) {
			if ( ! isset( self::MAP[ $alias ] ) ) continue;
			if ( null === $value ) delete_post_meta( $id, self::MAP[ $alias ] );
			else update_post_meta( $id, self::MAP[ $alias ], $value );
			$done[] = $alias;
		}
		return [ 'updated' => $done, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
