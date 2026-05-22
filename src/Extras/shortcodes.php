<?php
/**
 * Shortcode abilities for the Abilities API.
 *
 * @package Tropk\Mcp\Extras
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_abilities_api_init',
	static function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'tropk-shortcodes/list',
			[
				'label'               => 'Shortcodes: list registered',
     'category'            => 'tropk-core',
				'description'         => 'List all shortcode tags currently registered.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					global $shortcode_tags;
					return [ 'tags' => array_keys( (array) $shortcode_tags ), 'count' => count( (array) $shortcode_tags ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-shortcodes/exists',
			[
				'label'               => 'Shortcodes: check exists',
     'category'            => 'tropk-core',
				'description'         => 'Check whether a shortcode tag is registered.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'tag' ],
					'properties' => [ 'tag' => [ 'type' => 'string' ] ],
				],
				'execute_callback'    => static function ( array $input ): array {
					return [ 'tag' => (string) $input['tag'], 'exists' => shortcode_exists( (string) $input['tag'] ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-shortcodes/render',
			[
				'label'               => 'Shortcodes: render',
     'category'            => 'tropk-core',
				'description'         => 'Render a shortcode string and return the HTML output.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'content' ],
					'properties' => [ 'content' => [ 'type' => 'string', 'description' => 'Raw text containing shortcodes, e.g. "[gallery ids=\"1,2,3\"]".' ] ],
				],
				'execute_callback'    => static function ( array $input ): array {
					$out = do_shortcode( (string) $input['content'] );
					return [ 'html' => $out, 'length' => strlen( $out ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);
	},
	20
);
