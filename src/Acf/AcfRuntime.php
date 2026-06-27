<?php
declare(strict_types=1);

namespace Tropk\Mcp\Acf;

/**
 * Runtime detection + dispatch for ACF's native PHP API.
 *
 * Previously the four ACF abilities went through a thin REST bridge
 * (`/wp-json/angie-acf-mcp/v1/...`) — those routes came from a third-party
 * package the plugin used to vendor. Since the rewrite to 100% original
 * code that package is gone, so the bridge calls land on "route not
 * found". This class replaces the bridge with direct calls into ACF's
 * own internal post-type API, which is what ACF's admin UI uses too.
 *
 * Function availability matrix:
 *   - field groups + fields → ACF free (function_exists('acf_get_field_groups'))
 *   - post types + taxonomies → ACF Pro 6.1+
 *     (function_exists('acf_get_acf_post_types') / 'acf_get_acf_taxonomies').
 */
final class AcfRuntime {

	public static function is_active(): bool {
		return function_exists( 'acf_get_field_groups' );
	}

	/** ACF Pro 6.1+ exposes CPT/taxonomy registration helpers. */
	public static function has_post_types(): bool {
		return function_exists( 'acf_get_acf_post_types' )
			&& function_exists( 'acf_update_post_type' )
			&& function_exists( 'acf_delete_post_type' );
	}

	/** ACF Pro 6.1+ exposes taxonomy registration helpers. */
	public static function has_taxonomies(): bool {
		return function_exists( 'acf_get_acf_taxonomies' )
			&& function_exists( 'acf_update_taxonomy' )
			&& function_exists( 'acf_delete_taxonomy' );
	}

	public static function require_active(): void {
		if ( ! self::is_active() ) {
			throw new \RuntimeException( 'Advanced Custom Fields is not active on this site.' );
		}
	}

	public static function require_pro_post_types(): void {
		self::require_active();
		if ( ! self::has_post_types() ) {
			throw new \RuntimeException( 'ACF post-type tools require ACF Pro 6.1+ (Custom Post Types feature).' );
		}
	}

	public static function require_pro_taxonomies(): void {
		self::require_active();
		if ( ! self::has_taxonomies() ) {
			throw new \RuntimeException( 'ACF taxonomy tools require ACF Pro 6.1+ (Custom Taxonomies feature).' );
		}
	}

	public static function has_options_pages(): bool {
		return function_exists( 'acf_add_options_page' ) && function_exists( 'acf_get_options_pages' );
	}

	public static function require_pro_options_pages(): void {
		self::require_active();
		if ( ! self::has_options_pages() ) {
			throw new \RuntimeException( 'ACF options-page tools require ACF Pro (Options Pages feature).' );
		}
	}

	public static function has_blocks(): bool {
		return function_exists( 'acf_register_block_type' ) && function_exists( 'acf_get_block_types' );
	}

	public static function require_pro_blocks(): void {
		self::require_active();
		if ( ! self::has_blocks() ) {
			throw new \RuntimeException( 'ACF block tools require ACF Pro (ACF Blocks feature).' );
		}
	}

	public static function has_internal_post_type_helpers(): bool {
		return function_exists( 'acf_duplicate_internal_post_type' )
			&& function_exists( 'acf_trash_internal_post_type' )
			&& function_exists( 'acf_untrash_internal_post_type' )
			&& function_exists( 'acf_update_internal_post_type_active_status' );
	}

	/**
	 * Resolve the ACF internal post-type identifier for one of the four
	 * schema entities. Used by the shared activate/trash/duplicate/export
	 * helpers so each ability tells the runtime which ACF table to touch.
	 */
	public static function internal_post_type( string $entity ): string {
		return match ( $entity ) {
			'field-group' => 'acf-field-group',
			'post-type'   => 'acf-post-type',
			'taxonomy'    => 'acf-taxonomy',
			'options-page'=> 'acf-ui-options-page',
			default       => throw new \InvalidArgumentException( sprintf( 'Unknown ACF entity "%s".', $entity ) ),
		};
	}
}
