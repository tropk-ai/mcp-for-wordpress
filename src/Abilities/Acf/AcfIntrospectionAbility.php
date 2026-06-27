<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Acf\AcfRuntime;

/**
 * Schema introspection for the model. Lets the agent discover which
 * field types, location rules and operators are available on THIS site
 * (because Pro + add-on plugins can add custom ones) before authoring
 * field groups or location rules.
 */
final class AcfIntrospectionAbility implements Ability {

	public function slug(): string {
		return 'acf-introspect';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Inspect ACF field types and location rules', 'mcp-for-wordpress' ),
			'description'         => __( 'Enumerate registered field types (with their category, label and which Pro/addon registered them), and the location-rule parameter + operator catalog. Call this before authoring fields or field-group location rules so the model knows which values are valid on the current site.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'action' ],
				'properties'           => [
					'action' => [
						'type' => 'string',
						'enum' => [
							'list-field-types', 'get-field-type',
							'list-location-rule-types', 'list-location-rule-operators',
							'list-field-categories',
						],
					],
					'type'   => [
						'type'        => 'string',
						'description' => 'Field-type name (e.g. "text", "repeater", "flexible_content", "image", "relationship"). Required for get-field-type.',
					],
					'rule'   => [
						'type'        => 'string',
						'description' => 'Location-rule parameter name (e.g. "post_type", "user_role", "block"). Required for list-location-rule-operators.',
					],
				],
			],
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'show_in_rest' => true,
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function execute( array $input ): array {
		AcfRuntime::require_active();
		$action = (string) ( $input['action'] ?? '' );

		switch ( $action ) {
			case 'list-field-types':
				if ( ! function_exists( 'acf_get_field_types_info' ) ) {
					throw new \RuntimeException( 'acf_get_field_types_info is unavailable on this ACF version.' );
				}
				$types = (array) acf_get_field_types_info();
				return [ 'field_types' => array_values( $types ), 'count' => count( $types ) ];

			case 'get-field-type':
				$name = (string) ( $input['type'] ?? '' );
				if ( '' === $name ) {
					throw new \RuntimeException( 'type is required for get-field-type.' );
				}
				if ( ! function_exists( 'acf_get_field_type' ) ) {
					throw new \RuntimeException( 'acf_get_field_type is unavailable on this ACF version.' );
				}
				$ft = acf_get_field_type( $name );
				if ( ! is_object( $ft ) ) {
					throw new \RuntimeException( sprintf( 'Field type "%s" is not registered.', $name ) );
				}
				return [
					'name'      => (string) ( $ft->name ?? $name ),
					'label'     => (string) ( $ft->label ?? '' ),
					'category'  => (string) ( $ft->category ?? '' ),
					'defaults'  => isset( $ft->defaults ) ? (array) $ft->defaults : [],
					'supports'  => isset( $ft->supports ) ? (array) $ft->supports : [],
				];

			case 'list-location-rule-types':
				if ( ! function_exists( 'acf_get_location_rule_types' ) ) {
					throw new \RuntimeException( 'acf_get_location_rule_types is unavailable on this ACF version.' );
				}
				return [ 'rule_types' => (array) acf_get_location_rule_types() ];

			case 'list-location-rule-operators':
				$rule = (string) ( $input['rule'] ?? '' );
				if ( '' === $rule ) {
					throw new \RuntimeException( 'rule is required for list-location-rule-operators.' );
				}
				if ( ! function_exists( 'acf_get_location_rule_operators' ) ) {
					throw new \RuntimeException( 'acf_get_location_rule_operators is unavailable on this ACF version.' );
				}
				return [ 'operators' => (array) acf_get_location_rule_operators( [ 'param' => $rule ] ) ];

			case 'list-field-categories':
				if ( ! function_exists( 'acf_get_field_categories_i18n' ) ) {
					return [ 'categories' => [] ];
				}
				return [ 'categories' => (array) acf_get_field_categories_i18n() ];
		}

		throw new \RuntimeException( sprintf( 'Unknown action "%s".', $action ) );
	}
}
