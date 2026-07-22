<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Options;

use Tropk\Mcp\Abilities\AbstractAbility;

final class OptionsGetAbility extends AbstractAbility {

	private const DENY = [ 'auth_key', 'secure_auth_key', 'logged_in_key', 'nonce_key', 'auth_salt', 'secure_auth_salt', 'logged_in_salt', 'nonce_salt' ];

	public function slug(): string { return 'options-get'; }
	protected function meta(): array { return [
		'label' => __( 'Get a single option', 'mcp-for-wordpress' ),
		'description' => __( 'Reads a single option by key. Refuses to expose WordPress secret keys / salts.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'key' ],
		'properties'           => [ 'key' => [ 'type' => 'string', 'minLength' => 1 ] ],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'key' => [ 'type' => 'string' ], 'value' => [ 'description' => 'Current option value (any JSON type).' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		$key = (string) $input['key'];
		foreach ( self::DENY as $deny ) {
			if ( stripos( $key, $deny ) !== false ) {
				throw new \RuntimeException( 'Refusing to expose a WordPress secret/salt option.' );
			}
		}
		return [ 'key' => $key, 'value' => get_option( $key ) ];
	}
}
