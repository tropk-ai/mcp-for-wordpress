<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Acf\AcfRuntime;

/**
 * Manage ACF Pro Blocks — Gutenberg blocks defined declaratively by
 * ACF fields. Blocks are registered at runtime via
 * `acf_register_block_type($args)`; the registry is in-memory so
 * register-block is meant to be run from a theme/plugin bootstrap hook
 * (e.g. acf/init). list-blocks returns whatever is currently registered.
 */
final class AcfBlockAbility implements Ability {

	public function slug(): string {
		return 'acf-block';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Manage ACF blocks (Pro)', 'mcp-for-wordpress' ),
			'description'         => __( 'Register or inspect ACF Pro blocks (Gutenberg blocks whose UI is defined by ACF fields). list-blocks returns the in-memory registry. register-block adds one at runtime; the registry is wiped on each request, so persistent registrations belong in theme/plugin code on the `acf/init` action.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'action' ],
				'properties'           => [
					'action' => [
						'type' => 'string',
						'enum' => [ 'list-blocks', 'register-block', 'get-block' ],
					],
					'name'   => [
						'type'        => 'string',
						'description' => 'Block name (with namespace, e.g. "acf/testimonial"). Required for get-block.',
					],
					'block'  => [
						'type'                 => 'object',
						'description'          => 'Block args (passed to acf_register_block_type). Required keys: name (e.g. "acf/testimonial"), title, category. Optional: description, icon, keywords[], post_types[], parent[], ancestor[], mode (auto|preview|edit), align, align_text, align_content, full_height, render_callback, render_template, enqueue_assets, enqueue_style, enqueue_script, supports{ align, anchor, customClassName, html, multiple, jsx, color{background,text,gradient,link}, spacing{ margin, padding }, typography{ fontSize, lineHeight }, ariaLabel }, example{ attributes }, api_version (2 or 3), acf_block_version. Required for register-block.',
						'additionalProperties' => true,
					],
				],
			],
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				],
				'show_in_rest' => true,
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function execute( array $input ): array {
		AcfRuntime::require_pro_blocks();
		$action = (string) ( $input['action'] ?? '' );

		switch ( $action ) {
			case 'list-blocks':
				return [ 'blocks' => (array) acf_get_block_types() ];

			case 'get-block':
				$name = (string) ( $input['name'] ?? '' );
				if ( '' === $name ) {
					throw new \RuntimeException( 'name is required for get-block.' );
				}
				if ( ! function_exists( 'acf_get_block_type' ) ) {
					throw new \RuntimeException( 'acf_get_block_type is unavailable on this ACF version.' );
				}
				$block = acf_get_block_type( $name );
				if ( ! is_array( $block ) ) {
					throw new \RuntimeException( sprintf( 'Block "%s" is not registered.', $name ) );
				}
				return [ 'block' => $block ];

			case 'register-block':
				$block = (array) ( $input['block'] ?? [] );
				if ( empty( $block ) ) {
					throw new \RuntimeException( 'block is required for register-block.' );
				}
				$saved = acf_register_block_type( $block );
				if ( false === $saved ) {
					throw new \RuntimeException( 'ACF refused the block registration (name missing or already registered).' );
				}
				return [ 'registered' => true, 'block' => is_array( $saved ) ? $saved : $block ];
		}

		throw new \RuntimeException( sprintf( 'Unknown action "%s".', $action ) );
	}
}
