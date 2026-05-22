<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities;

final class SiteInfoAbility implements Ability {

	public function slug(): string {
		return 'site-info';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Get site information', 'mcp-for-wordpress' ),
			'description'         => __( 'Returns site identity, WordPress version, active theme, languages, and timezone. Read-only.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'properties'           => new \stdClass(),
				'additionalProperties' => false,
			],
			'output_schema'       => [
				'$schema'    => 'https://json-schema.org/draft/2020-12/schema',
				'type'       => 'object',
				'properties' => [
					'name'        => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
					'url'         => [ 'type' => 'string', 'format' => 'uri' ],
					'admin_email' => [ 'type' => 'string', 'format' => 'email' ],
					'language'    => [ 'type' => 'string' ],
					'timezone'    => [ 'type' => 'string' ],
					'wp_version'  => [ 'type' => 'string' ],
					'php_version' => [ 'type' => 'string' ],
					'theme'       => [
						'type'       => 'object',
						'properties' => [
							'name'    => [ 'type' => 'string' ],
							'version' => [ 'type' => 'string' ],
							'stylesheet' => [ 'type' => 'string' ],
						],
					],
					'multisite'   => [ 'type' => 'boolean' ],
				],
				'required'   => [ 'name', 'url', 'wp_version' ],
			],
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations' => [
					'readonly'   => true,
					'idempotent' => true,
				],
				'show_in_rest' => true,
			],
		];
	}

	public function authorize(): bool {
		return current_user_can( 'read' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function execute(): array {
		global $wp_version;

		$theme = wp_get_theme();

		return [
			'name'        => (string) get_bloginfo( 'name' ),
			'description' => (string) get_bloginfo( 'description' ),
			'url'         => (string) get_bloginfo( 'url' ),
			'admin_email' => (string) get_bloginfo( 'admin_email' ),
			'language'    => (string) get_locale(),
			'timezone'    => (string) wp_timezone_string(),
			'wp_version'  => (string) $wp_version,
			'php_version' => PHP_VERSION,
			'theme'       => [
				'name'       => (string) $theme->get( 'Name' ),
				'version'    => (string) $theme->get( 'Version' ),
				'stylesheet' => (string) $theme->get_stylesheet(),
			],
			'multisite'   => is_multisite(),
		];
	}
}
