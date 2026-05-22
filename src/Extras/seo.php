<?php
/**
 * Extra SEO abilities for the Abilities API.
 *
 * Most SEO operations come from the vendored `rankmath/*` namespace.
 * This file only fills the gaps RankMath doesn't cover natively:
 * robots.txt CRUD via WordPress's filter on the virtual robots.txt
 * (stored in our own option) and a quick canonical-URL setter.
 *
 * @package Tropk\Mcp\Extras
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'tropk_mcp_robots_filter' ) ) {
	function tropk_mcp_robots_filter( string $output ): string {
		$extra = (string) get_option( 'tropk_mcp_robots_extra', '' );
		if ( '' === $extra ) {
			return $output;
		}
		return rtrim( $output, "\n" ) . "\n\n" . $extra . "\n";
	}
	add_filter( 'robots_txt', 'tropk_mcp_robots_filter', 20, 1 );
}

add_action(
	'wp_abilities_api_init',
	static function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'tropk-seo/get-robots-txt',
			[
				'label'               => 'SEO: get robots.txt',
     'category'            => 'tropk-core',
				'description'         => 'Returns what WordPress would serve at /robots.txt right now (core output + any plugin/filter additions including our own extra).',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					$response = wp_remote_get( home_url( '/robots.txt' ), [ 'timeout' => 5 ] );
					if ( is_wp_error( $response ) ) {
						return [ 'live_fetch_failed' => true, 'managed_extra' => (string) get_option( 'tropk_mcp_robots_extra', '' ) ];
					}
					return [
						'content'       => wp_remote_retrieve_body( $response ),
						'status'        => wp_remote_retrieve_response_code( $response ),
						'managed_extra' => (string) get_option( 'tropk_mcp_robots_extra', '' ),
					];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-seo/update-robots-txt',
			[
				'label'               => 'SEO: update robots.txt extras',
     'category'            => 'tropk-core',
				'description'         => 'Appends custom rules to the virtual robots.txt. Stored in option `tropk_mcp_robots_extra` and merged into core output via the `robots_txt` filter. Pass empty string to clear.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'extra' ],
					'properties' => [
						'extra' => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$value = (string) $input['extra'];
					update_option( 'tropk_mcp_robots_extra', $value, false );
					return [ 'updated' => true, 'managed_extra' => $value ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-seo/set-canonical',
			[
				'label'               => 'SEO: set canonical URL',
     'category'            => 'tropk-core',
				'description'         => 'Set the rel=canonical URL for a post (stored as `_yoast_wpseo_canonical`, `rank_math_canonical_url` and `_aioseop_canonical_url` for cross-plugin support).',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'post_id', 'url' ],
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'minimum' => 1 ],
						'url'     => [ 'type' => 'string', 'format' => 'uri' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					$id  = (int) $input['post_id'];
					$url = esc_url_raw( (string) $input['url'] );
					update_post_meta( $id, '_yoast_wpseo_canonical', $url );
					update_post_meta( $id, 'rank_math_canonical_url', $url );
					update_post_meta( $id, '_aioseop_canonical_url', $url );
					return [ 'updated' => true, 'post_id' => $id, 'canonical' => $url ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_others_posts' ) || current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);
	},
	20
);
