<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\RankMath;
use Tropk\Mcp\Abilities\AbstractAbility;
final class RankMathCreateRedirectionAbility extends AbstractAbility {
	public function slug(): string { return 'rankmath-create-redirection'; }
	protected function meta(): array { return [ 'label' => __( 'Create a Rank Math redirection', 'mcp-for-wordpress' ), 'description' => __( 'Adds a row to the {prefix}rank_math_redirections table. Requires Rank Math Redirections module active.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'source', 'destination' ], 'properties' => [ 'source' => [ 'type' => 'string' ], 'destination' => [ 'type' => 'string' ], 'type' => [ 'type' => 'integer', 'enum' => [ 301, 302, 307, 410, 451 ], 'default' => 301 ], 'match_type' => [ 'type' => 'string', 'enum' => [ 'exact', 'contains', 'start', 'end', 'regex' ], 'default' => 'exact' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'created' => [ 'type' => 'boolean' ], 'id' => [ 'type' => [ 'integer', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_redirections';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			throw new \RuntimeException( 'Rank Math Redirections table not found — make sure the Redirections module is enabled.' );
		}
		$ok = $wpdb->insert( $table, [
			'sources'         => maybe_serialize( [ [ 'pattern' => (string) $input['source'], 'comparison' => (string) ( $input['match_type'] ?? 'exact' ), 'ignore' => '' ] ] ),
			'url_to'          => (string) $input['destination'],
			'header_code'     => (int) ( $input['type'] ?? 301 ),
			'status'          => 'active',
			'created'         => current_time( 'mysql' ),
			'updated'         => current_time( 'mysql' ),
		] );
		return [ 'created' => (bool) $ok, 'id' => $ok ? (int) $wpdb->insert_id : null ];
	}
}
