<?php
declare(strict_types=1);

namespace Tropk\Mcp\Elementor;

/**
 * Centralized, correct (de)serialization for Elementor post metas.
 *
 * Elementor stores its two main metas in DIFFERENT formats and mixing them
 * up is a recurring source of fatals:
 *
 *   - `_elementor_data` is a JSON *string* (Elementor json_decodes it).
 *   - `_elementor_page_settings` (and the kit settings meta) is a NATIVE
 *     PHP array that WordPress serializes via maybe_serialize(). Elementor's
 *     Document::get_saved_settings() reads it back with get_post_meta() and
 *     expects an array; when a JSON *string* was written instead it array-
 *     accesses a string and the request 500s.
 *
 * Every Elementor meta write/read should funnel through here so the two
 * formats can never be confused again.
 */
final class ElementorMeta {

	public const DATA_KEY          = '_elementor_data';
	public const PAGE_SETTINGS_KEY = '_elementor_page_settings';

	/**
	 * Metas Elementor persists as NATIVE (WordPress-serialized) arrays —
	 * never JSON strings. Writing JSON into one of these breaks Elementor.
	 *
	 * @var array<int, string>
	 */
	private const ARRAY_METAS = [
		self::PAGE_SETTINGS_KEY,
	];

	/**
	 * Is this an Elementor meta that must be stored as a native array
	 * rather than a JSON string?
	 */
	public static function is_array_meta( string $key ): bool {
		return in_array( $key, self::ARRAY_METAS, true );
	}

	/**
	 * Read `_elementor_page_settings` as an array regardless of how it was
	 * persisted: native array (correct), a legacy JSON string (the bug this
	 * class fixes), or a raw PHP-serialized string.
	 *
	 * @return array<string, mixed>
	 */
	public static function read_page_settings( int $post_id ): array {
		return self::to_array( get_post_meta( $post_id, self::PAGE_SETTINGS_KEY, true ) );
	}

	/**
	 * Write `_elementor_page_settings` as a NATIVE array so Elementor's
	 * get_saved_settings() can read it, and drop the compiled CSS cache so
	 * the change is recompiled on next render.
	 *
	 * @param array<string, mixed> $settings
	 */
	public static function write_page_settings( int $post_id, array $settings ): void {
		self::write_array_meta( $post_id, self::PAGE_SETTINGS_KEY, $settings );
		delete_post_meta( $post_id, '_elementor_css' );
	}

	/**
	 * Write a native-array Elementor meta. wp_slash() is applied because
	 * update_post_meta() runs wp_unslash() on the value before storing — so
	 * slashing first round-trips nested strings (custom CSS, quotes,
	 * backslashes) through intact.
	 *
	 * @param array<string, mixed> $value
	 */
	public static function write_array_meta( int $post_id, string $key, array $value ): void {
		update_post_meta( $post_id, $key, wp_slash( $value ) );
	}

	/**
	 * Normalize a value that is supposed to be an Elementor settings array,
	 * accepting native arrays, legacy JSON strings, or PHP-serialized
	 * strings. Anything unrecognized normalizes to an empty array.
	 *
	 * JSON is tried before unserialize() so the common (and bug-prone) JSON
	 * string case never depends on a WordPress function being loaded.
	 *
	 * @param mixed $value
	 * @return array<string, mixed>
	 */
	public static function to_array( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
			if ( function_exists( 'maybe_unserialize' ) ) {
				$unser = maybe_unserialize( $value );
				if ( is_array( $unser ) ) {
					return $unser;
				}
			}
		}
		return [];
	}
}
