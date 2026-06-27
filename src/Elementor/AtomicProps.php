<?php
declare(strict_types=1);

namespace Tropk\Mcp\Elementor;

/**
 * Helpers for Elementor V4 "atomic" typed properties.
 *
 * In V4 every setting/style value is a typed envelope:
 *
 *     { "$$type": "<key>", "value": <scalar|object|array>, "disabled"?: true }
 *
 * Values compose recursively — e.g. a `dimensions` prop whose four sides are
 * each a `size` prop whose value is `{ unit, size }`. The single exception is
 * `classes`, whose value is a plain array of class-name strings (not a list
 * of envelopes).
 *
 * This class is the one place that builds and normalizes those envelopes so
 * the rest of the plugin never hand-rolls (and never accidentally stringifies)
 * V4 prop values.
 */
final class AtomicProps {

	/**
	 * Build a typed-prop envelope.
	 *
	 * @param mixed $value
	 * @return array<string, mixed>
	 */
	public static function wrap( string $type, $value ): array {
		return [ '$$type' => $type, 'value' => $value ];
	}

	/** A `string` prop. */
	public static function string( string $value ): array {
		return self::wrap( 'string', $value );
	}

	/** A `number` prop. */
	public static function number( float|int $value ): array {
		return self::wrap( 'number', $value );
	}

	/** A `boolean` prop. */
	public static function boolean( bool $value ): array {
		return self::wrap( 'boolean', $value );
	}

	/** A `color` prop (hex/rgb(a)/hsl(a)/name). */
	public static function color( string $value ): array {
		return self::wrap( 'color', $value );
	}

	/**
	 * A `classes` prop — a PLAIN array of class-name strings (the one prop
	 * whose items are bare strings rather than nested envelopes).
	 *
	 * @param array<int, string> $class_names
	 */
	public static function classes( array $class_names ): array {
		return self::wrap( 'classes', array_values( array_map( 'strval', $class_names ) ) );
	}

	/** A `size` prop: `{ unit, size }`. */
	public static function size( float|int|string $size, string $unit = 'px' ): array {
		return self::wrap( 'size', [ 'size' => $size, 'unit' => $unit ] );
	}

	/**
	 * Is this value a typed-prop envelope (`{ $$type, value }`)?
	 *
	 * @param mixed $value
	 */
	public static function is_envelope( $value ): bool {
		return is_array( $value )
			&& array_key_exists( '$$type', $value )
			&& array_key_exists( 'value', $value )
			&& is_string( $value['$$type'] );
	}

	/**
	 * Normalize an incoming setting/style value into native structured data.
	 *
	 * The MCP transport (and some clients) can deliver a typed prop as a JSON
	 * *string* — e.g. the literal text `{"$$type":"classes","value":["mb-x"]}`
	 * — instead of a structured object. Stored verbatim that becomes a quoted
	 * string in `_elementor_data` and Elementor's atomic schema parser rejects
	 * it. This decodes any JSON object/array string back into a native array
	 * (recursively, so nested envelopes delivered as strings are healed too).
	 * Plain scalar settings (a real title, a number, a bool) pass through
	 * untouched.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function normalize_value( $value ) {
		if ( is_string( $value ) ) {
			$trimmed = trim( $value );
			if ( '' !== $trimmed && ( '{' === $trimmed[0] || '[' === $trimmed[0] ) ) {
				$decoded = json_decode( $trimmed, true );
				if ( is_array( $decoded ) ) {
					return self::normalize_value( $decoded );
				}
			}
			return $value;
		}
		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $k => $v ) {
				$out[ $k ] = self::normalize_value( $v );
			}
			return $out;
		}
		return $value;
	}
}
