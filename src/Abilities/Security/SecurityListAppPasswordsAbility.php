<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Security;
use Tropk\Mcp\Abilities\AbstractAbility;
final class SecurityListAppPasswordsAbility extends AbstractAbility {
	public function slug(): string { return 'security-list-application-passwords'; }
	protected function meta(): array { return [ 'label' => __( 'List application passwords', 'mcp-for-wordpress' ), 'description' => __( 'Returns every Application Password issued for the current user (names + creation date — never the password itself).', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'read' ); }
	public function execute( array $input = [] ): array { 
		if ( ! class_exists( "WP_Application_Passwords" ) ) return [ "result" => [ "passwords" => [] ] ];
		$out = [];
		foreach ( \WP_Application_Passwords::get_user_application_passwords( get_current_user_id() ) as $p ) {
			$out[] = [ "uuid" => (string) ( $p["uuid"] ?? "" ), "name" => (string) ( $p["name"] ?? "" ), "created" => (int) ( $p["created"] ?? 0 ), "last_used" => $p["last_used"] ?? null ];
		}
		return [ "result" => [ "passwords" => $out ] ]; }
}
