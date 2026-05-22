<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorReplaceUrlsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-replace-urls'; }
	protected function meta(): array { return [
		'label'       => __( 'Replace URLs in Elementor data', 'mcp-for-wordpress' ),
		'description' => __( "Site-wide URL replacement inside Elementor data via Elementor's built-in Utils::replace_urls(). Useful after a domain migration.", 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'from', 'to' ],
		'properties'           => [
			'from' => [ 'type' => 'string', 'minLength' => 1 ],
			'to'   => [ 'type' => 'string', 'minLength' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'from' => [ 'type' => 'string' ], 'to' => [ 'type' => 'string' ], 'message' => [ 'type' => 'string' ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$from = (string) ( $input['from'] ?? '' );
		$to   = (string) ( $input['to'] ?? '' );
		if ( '' === $from || '' === $to ) {
			throw new \RuntimeException( 'Both from and to are required.' );
		}
		if ( ! class_exists( '\\Elementor\\Utils' ) || ! method_exists( '\\Elementor\\Utils', 'replace_urls' ) ) {
			throw new \RuntimeException( 'Elementor\\Utils::replace_urls is not available.' );
		}
		try {
			$msg = (string) \Elementor\Utils::replace_urls( $from, $to );
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( $e->getMessage() );
		}
		return [ 'from' => $from, 'to' => $to, 'message' => $msg ];
	}
}
