<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\RankMath;
use Tropk\Mcp\Abilities\AbstractAbility;
final class RankMathGetMetaAbility extends AbstractAbility {
	public function slug(): string { return 'rankmath-get-meta'; }
	protected function meta(): array { return [ 'label' => __( 'Get Rank Math meta', 'mcp-for-wordpress' ), 'description' => __( 'Returns Rank Math SEO meta for a post (title, description, focus keyword, canonical, robots, social cards).', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'meta' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$id = (int) $input['post_id'];
		$keys = [ 'title' => 'rank_math_title', 'description' => 'rank_math_description', 'focus_keyword' => 'rank_math_focus_keyword', 'canonical' => 'rank_math_canonical_url', 'robots' => 'rank_math_robots', 'pillar' => 'rank_math_pillar_content', 'breadcrumb' => 'rank_math_breadcrumb_title', 'fb_title' => 'rank_math_facebook_title', 'fb_description' => 'rank_math_facebook_description', 'twitter_title' => 'rank_math_twitter_title', 'twitter_description' => 'rank_math_twitter_description' ];
		$out = [];
		foreach ( $keys as $alias => $key ) $out[ $alias ] = get_post_meta( $id, $key, true );
		return [ 'meta' => $out ];
	}
}
