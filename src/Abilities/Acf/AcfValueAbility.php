<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Acf\AcfRuntime;

/**
 * Read and write ACF field VALUES on any ACF target (post, page, custom
 * post type, user, term, options page, comment, widget, block).
 *
 * Uses ACF's get_field / update_field / delete_field which go through
 * ACF's value pipeline — so:
 *   - image/file/gallery values come back as full attachment arrays;
 *   - dates use the configured display format;
 *   - repeater + flexible_content arrays are properly nested;
 *   - relationship/post_object return WP_Post (or its array form);
 *   - conditional logic is respected.
 *
 * Going through the generic posts/meta tools writes raw _meta_ rows and
 * BYPASSES this pipeline, which is why update-field is its own tool.
 */
final class AcfValueAbility implements Ability {

	public function slug(): string {
		return 'acf-value';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Read & write ACF field values', 'mcp-for-wordpress' ),
			'description'         => __( 'Read or write the actual VALUE of an ACF field on any ACF target (post, page, custom post type, user, term, options page, comment, widget, block). Goes through ACF\'s value pipeline so image/file/gallery, repeater, flexible_content, date and relationship fields come back in their proper shape.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'action', 'target' ],
				'properties'           => [
					'action'       => [
						'type' => 'string',
						'enum' => [ 'get-value', 'get-values', 'get-field-object', 'update-value', 'delete-value', 'has-value' ],
					],
					'target'       => [
						'type'        => 'string',
						'description' => 'ACF target id. Numeric post ID for posts/pages/CPTs, or one of: "user_<id>" / "term_<id>" / "comment_<id>" / "options" / "option" / "<options_page_menu_slug>" / "widget_<id>" / "block_<id>". `options` reads/writes the default options page; pass the menu_slug of a specific options page to target it.',
					],
					'selector'     => [
						'type'        => 'string',
						'description' => 'Field name or key (e.g. "hero_image" or "field_abc123"). For repeater/group sub-fields use the dot path: "address_repeater_0_street". Required for get-value, get-field-object, update-value, delete-value, has-value.',
					],
					'value'        => [
						'description' => 'New value for update-value. Shape depends on field type — image: attachment ID or {ID,url,…}; relationship: ID or [IDs]; repeater: [{sub_field_name: value, …}, …]; date: "YYYY-MM-DD" or "Ymd"; true_false: bool; choice fields: the value of the chosen choice; etc. Use get-field-object first if unsure.',
					],
					'format_value' => [
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Pass false to bypass ACF\'s format_value filter and read the raw stored value (numeric IDs instead of attachment arrays, raw repeater rows, etc.).',
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
		$target = (string) ( $input['target'] ?? '' );
		// Treat numeric targets as a post we can map to edit_post; the
		// others (user_*, term_*, options, comment_*, block_*) gate on
		// edit_others_posts which is the most consistent ACF admin cap.
		if ( ctype_digit( $target ) ) {
			return current_user_can( 'edit_post', (int) $target );
		}
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function execute( array $input ): array {
		AcfRuntime::require_active();
		$action       = (string) ( $input['action'] ?? '' );
		$target       = (string) ( $input['target'] ?? '' );
		$selector     = (string) ( $input['selector'] ?? '' );
		$format_value = isset( $input['format_value'] ) ? (bool) $input['format_value'] : true;

		switch ( $action ) {
			case 'get-value':
				if ( '' === $selector ) {
					throw new \RuntimeException( 'selector is required for get-value.' );
				}
				return [ 'value' => get_field( $selector, $target, $format_value ) ];

			case 'get-values':
				return [ 'values' => (array) get_fields( $target, $format_value ) ];

			case 'get-field-object':
				if ( '' === $selector ) {
					throw new \RuntimeException( 'selector is required for get-field-object.' );
				}
				$obj = get_field_object( $selector, $target, $format_value );
				return [ 'field' => is_array( $obj ) ? $obj : null ];

			case 'update-value':
				if ( '' === $selector ) {
					throw new \RuntimeException( 'selector is required for update-value.' );
				}
				if ( ! array_key_exists( 'value', $input ) ) {
					throw new \RuntimeException( 'value is required for update-value (pass null to clear).' );
				}
				$ok = (bool) update_field( $selector, $input['value'], $target );
				return [ 'updated' => $ok ];

			case 'delete-value':
				if ( '' === $selector ) {
					throw new \RuntimeException( 'selector is required for delete-value.' );
				}
				$ok = (bool) delete_field( $selector, $target );
				return [ 'deleted' => $ok ];

			case 'has-value':
				if ( '' === $selector ) {
					throw new \RuntimeException( 'selector is required for has-value.' );
				}
				$raw = get_field( $selector, $target, false );
				return [ 'has_value' => ! ( null === $raw || '' === $raw || ( is_array( $raw ) && empty( $raw ) ) ) ];
		}

		throw new \RuntimeException( sprintf( 'Unknown action "%s".', $action ) );
	}
}
