<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\RankMath;
use Tropk\Mcp\Abilities\AbstractAbility;
final class RankMathBulkGetMetaAbility extends AbstractAbility {
	public function slug(): string { return 'rankmath-bulk-get-meta'; }
	protected function meta(): array { return [ 'label' => __( 'Bulk-read Rank Math meta', 'mcp-for-wordpress' ), 'description' => __( 'Returns Rank Math SEO meta for many posts at once.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_ids' ], 'properties' => [ 'post_ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'maxItems' => 100 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'items' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$out = [];
		foreach ( (array) $input['post_ids'] as $id ) {
			$id = (int) $id;
			if ( ! current_user_can( 'edit_post', $id ) ) continue;
			$out[] = [
				'post_id' => $id,
				'title' => (string) get_post_meta( $id, 'rank_math_title', true ),
				'description' => (string) get_post_meta( $id, 'rank_math_description', true ),
				'focus_keyword' => (string) get_post_meta( $id, 'rank_math_focus_keyword', true ),
			];
		}
		return [ 'items' => $out ];
	}
}
