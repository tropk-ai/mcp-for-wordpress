<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\RankMath;
use Tropk\Mcp\Abilities\AbstractAbility;
final class RankMathDeleteRedirectionsAbility extends AbstractAbility {
	public function slug(): string { return 'rankmath-delete-redirections'; }
	protected function meta(): array { return [ 'label' => __( 'Delete Rank Math redirections', 'mcp-for-wordpress' ), 'description' => __( 'Removes one or more redirection rows by ID.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'ids' ], 'properties' => [ 'ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deleted' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_redirections';
		$n = 0;
		foreach ( (array) $input['ids'] as $id ) {
			$n += (int) $wpdb->delete( $table, [ 'id' => (int) $id ], [ '%d' ] );
		}
		return [ 'deleted' => $n ];
	}
}
