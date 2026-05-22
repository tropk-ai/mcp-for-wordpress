<?php
declare(strict_types=1);

namespace Tropk\Mcp\Elementor;

/**
 * Loads and mutates the Elementor JSON tree (`_elementor_data`) for a
 * single post. Atomic widgets (Editor V4, "a-*" or "e-*" widgetType) are
 * treated as opaque: their settings are never decoded and never created
 * from scratch.
 */
final class ElementorPage {

	private const TEXT_FIELDS_GLOBAL = [
		'title', 'header', 'subheader', 'sub_title', 'subtitle',
		'text', 'editor', 'content', 'description', 'caption',
		'label', 'heading', 'paragraph',
	];

	/** @var array<string, array<int, string>> */
	private const TEXT_FIELDS_BY_WIDGET = [
		'heading'        => [ 'title' ],
		'text-editor'    => [ 'editor' ],
		'button'         => [ 'text' ],
		'image-box'      => [ 'title_text', 'description_text' ],
		'icon-box'       => [ 'title_text', 'description_text' ],
		'star-rating'    => [ 'title' ],
		'call-to-action' => [ 'title', 'description', 'button' ],
		'testimonial'    => [ 'testimonial_content', 'testimonial_name', 'testimonial_job' ],
		'flip-box'       => [ 'title_text_a', 'description_text_a', 'title_text_b', 'description_text_b', 'button_text' ],
		'counter'        => [ 'title' ],
		'progress'       => [ 'title', 'inner_text' ],
		'alert'          => [ 'alert_title', 'alert_description' ],
		'price-table'    => [ 'heading', 'sub_heading', 'price', 'footer_additional_info' ],
	];

	private int $post_id;
	/** @var array<int, array<string, mixed>> */
	private array $data;

	private function __construct( int $post_id, array $data ) {
		$this->post_id = $post_id;
		$this->data    = $data;
	}

	public static function load( int $post_id ): self {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( '' === $raw || null === $raw ) {
			return new self( $post_id, [] );
		}
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $decoded ) ) {
			throw new \RuntimeException( sprintf( 'Post %d has malformed _elementor_data.', $post_id ) );
		}
		return new self( $post_id, $decoded );
	}

	public static function is_elementor_post( int $post_id ): bool {
		return 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true );
	}

	public function post_id(): int {
		return $this->post_id;
	}

	public function is_empty(): bool {
		return [] === $this->data;
	}

	public function data(): array {
		return $this->data;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function widgets(): array {
		$out = [];
		$this->walk(
			$this->data,
			[],
			static function ( array $node, array $path ) use ( &$out ): void {
				if ( ( $node['elType'] ?? '' ) !== 'widget' ) {
					return;
				}
				$type   = (string) ( $node['widgetType'] ?? '' );
				$atomic = self::is_atomic_widget_type( $type );
				$out[]  = [
					'id'         => (string) ( $node['id'] ?? '' ),
					'widgetType' => $type,
					'atomic'     => $atomic,
					'depth'      => count( $path ),
					'snippet'    => $atomic ? null : self::extract_snippet( $type, (array) ( $node['settings'] ?? [] ) ),
				];
			}
		);
		return $out;
	}

	public function find_widget( string $id ): ?array {
		$found = null;
		$this->walk(
			$this->data,
			[],
			static function ( array $node ) use ( $id, &$found ): void {
				if ( null !== $found ) {
					return;
				}
				if ( ( $node['id'] ?? '' ) === $id ) {
					$found = $node;
				}
			}
		);
		return $found;
	}

	public function outline( int $max_bytes = 2048 ): string {
		$lines = [];
		$this->walk_tree(
			$this->data,
			0,
			static function ( array $node, int $depth ) use ( &$lines ): void {
				$indent  = str_repeat( '  ', $depth );
				$el_type = (string) ( $node['elType'] ?? 'unknown' );
				$id      = (string) ( $node['id'] ?? '?' );
				if ( 'widget' === $el_type ) {
					$wt = (string) ( $node['widgetType'] ?? 'widget' );
					if ( self::is_atomic_widget_type( $wt ) ) {
						$lines[] = sprintf( '%s%s[%s] (atomic, opaque)', $indent, $wt, $id );
						return;
					}
					$s = self::extract_snippet( $wt, (array) ( $node['settings'] ?? [] ) );
					$lines[] = sprintf( '%s%s[%s]%s', $indent, $wt, $id, $s ? ': ' . $s : '' );
					return;
				}
				$lines[] = sprintf( '%s%s[%s]', $indent, $el_type, $id );
			}
		);

		$out = '';
		foreach ( $lines as $line ) {
			$candidate = '' === $out ? $line : $out . "\n" . $line;
			if ( strlen( $candidate ) > $max_bytes ) {
				$out .= "\n... (truncated)";
				break;
			}
			$out = $candidate;
		}
		return $out;
	}

	public function replace_text( string $find, string $replace, ?array $widget_types = null, bool $case_sensitive = false ): int {
		if ( '' === $find ) {
			return 0;
		}
		$count = 0;
		$this->walk_mutable(
			$this->data,
			static function ( array &$node ) use ( $find, $replace, $widget_types, $case_sensitive, &$count ): void {
				if ( ( $node['elType'] ?? '' ) !== 'widget' ) {
					return;
				}
				$type = (string) ( $node['widgetType'] ?? '' );
				if ( self::is_atomic_widget_type( $type ) ) {
					return;
				}
				if ( null !== $widget_types && ! in_array( $type, $widget_types, true ) ) {
					return;
				}
				if ( ! isset( $node['settings'] ) || ! is_array( $node['settings'] ) ) {
					return;
				}
				$fields = self::resolve_text_fields_for( $type );
				$count += self::replace_in_fields( $node['settings'], $fields, $find, $replace, $case_sensitive );
			}
		);
		return $count;
	}

	public function update_widget_setting( string $widget_id, string $key, $value ): bool {
		$updated = false;
		$this->walk_mutable(
			$this->data,
			static function ( array &$node ) use ( $widget_id, $key, $value, &$updated ): void {
				if ( ( $node['id'] ?? '' ) !== $widget_id ) {
					return;
				}
				if ( ! isset( $node['settings'] ) || ! is_array( $node['settings'] ) ) {
					$node['settings'] = [];
				}
				$node['settings'][ $key ] = $value;
				$updated = true;
			}
		);
		return $updated;
	}

	public function delete_widget( string $widget_id ): bool {
		$removed = false;
		$this->data = $this->prune( $this->data, $widget_id, $removed );
		return $removed;
	}

	private function prune( array $nodes, string $target, bool &$removed ): array {
		$out = [];
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( ( $node['id'] ?? '' ) === $target ) {
				$removed = true;
				continue;
			}
			if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$node['elements'] = $this->prune( $node['elements'], $target, $removed );
			}
			$out[] = $node;
		}
		return $out;
	}

	public function save(): void {
		$encoded = wp_json_encode( $this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			throw new \RuntimeException( 'Failed to encode Elementor data.' );
		}
		update_post_meta( $this->post_id, '_elementor_data', wp_slash( $encoded ) );
		$this->flush_css();
	}

	public function flush_css(): void {
		delete_post_meta( $this->post_id, '_elementor_css' );
		delete_option( '_elementor_global_css' );
		if ( class_exists( '\\Elementor\\Plugin' ) ) {
			$el = \Elementor\Plugin::$instance ?? null;
			if ( $el && isset( $el->files_manager ) && method_exists( $el->files_manager, 'clear_cache' ) ) {
				$el->files_manager->clear_cache();
			}
		}
	}

	public function clone_to_new_post( string $title, string $status = 'draft', ?int $author_id = null ): int {
		$source = get_post( $this->post_id );
		if ( ! $source instanceof \WP_Post ) {
			throw new \RuntimeException( sprintf( 'Source post %d not found.', $this->post_id ) );
		}
		$new_id = wp_insert_post(
			[
				'post_type'    => $source->post_type,
				'post_title'   => $title,
				'post_status'  => $status,
				'post_content' => $source->post_content,
				'post_author'  => null !== $author_id ? $author_id : (int) $source->post_author,
				'post_parent'  => (int) $source->post_parent,
				'menu_order'   => (int) $source->menu_order,
			],
			true
		);
		if ( is_wp_error( $new_id ) ) {
			throw new \RuntimeException( $new_id->get_error_message() );
		}
		$cloned = $this->data;
		$this->regenerate_ids( $cloned );
		$encoded = wp_json_encode( $cloned, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		update_post_meta( (int) $new_id, '_elementor_data', wp_slash( (string) $encoded ) );
		update_post_meta( (int) $new_id, '_elementor_edit_mode', 'builder' );
		foreach ( [ '_elementor_template_type', '_elementor_version', '_elementor_page_settings', '_elementor_pro_version' ] as $k ) {
			$v = get_post_meta( $this->post_id, $k, true );
			if ( '' !== $v && null !== $v ) {
				update_post_meta( (int) $new_id, $k, $v );
			}
		}
		return (int) $new_id;
	}

	public static function is_atomic_widget_type( string $widget_type ): bool {
		if ( '' === $widget_type ) {
			return false;
		}
		return str_starts_with( $widget_type, 'a-' ) || str_starts_with( $widget_type, 'e-' );
	}

	public static function resolve_text_fields_for( string $widget_type ): array {
		return self::TEXT_FIELDS_BY_WIDGET[ $widget_type ] ?? self::TEXT_FIELDS_GLOBAL;
	}

	public static function replace_in_fields( array &$settings, array $fields, string $find, string $replace, bool $case_sensitive ): int {
		$count = 0;
		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field, $settings ) ) continue;
			$v = $settings[ $field ];
			if ( is_string( $v ) ) {
				$new = $case_sensitive ? str_replace( $find, $replace, $v, $hits ) : str_ireplace( $find, $replace, $v, $hits );
				if ( $hits > 0 ) {
					$settings[ $field ] = $new;
					$count += $hits;
				}
			}
		}
		foreach ( $settings as &$v ) {
			if ( is_array( $v ) && self::looks_like_indexed_array( $v ) ) {
				foreach ( $v as &$item ) {
					if ( is_array( $item ) ) {
						$count += self::replace_in_fields( $item, self::TEXT_FIELDS_GLOBAL, $find, $replace, $case_sensitive );
					}
				}
				unset( $item );
			}
		}
		unset( $v );
		return $count;
	}

	private static function looks_like_indexed_array( array $a ): bool {
		if ( [] === $a ) return false;
		return array_keys( $a ) === range( 0, count( $a ) - 1 );
	}

	private static function extract_snippet( string $widget_type, array $settings, int $max = 80 ): string {
		foreach ( self::resolve_text_fields_for( $widget_type ) as $f ) {
			if ( isset( $settings[ $f ] ) && is_string( $settings[ $f ] ) && '' !== $settings[ $f ] ) {
				$t = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $settings[ $f ] ) ) ?? '' );
				if ( '' === $t ) continue;
				if ( mb_strlen( $t ) > $max ) {
					$t = mb_substr( $t, 0, $max - 1 ) . '…';
				}
				return $t;
			}
		}
		return '';
	}

	private function walk( array $nodes, array $path, callable $cb ): void {
		foreach ( $nodes as $i => $node ) {
			if ( ! is_array( $node ) ) continue;
			$p = array_merge( $path, [ $i ] );
			$cb( $node, $p );
			if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$this->walk( $node['elements'], $p, $cb );
			}
		}
	}

	private function walk_mutable( array &$nodes, callable $cb ): void {
		foreach ( $nodes as &$node ) {
			if ( ! is_array( $node ) ) continue;
			$cb( $node );
			if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$this->walk_mutable( $node['elements'], $cb );
			}
		}
		unset( $node );
	}

	private function walk_tree( array $nodes, int $depth, callable $cb ): void {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) continue;
			$cb( $node, $depth );
			if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$this->walk_tree( $node['elements'], $depth + 1, $cb );
			}
		}
	}

	private function regenerate_ids( array &$nodes ): void {
		foreach ( $nodes as &$node ) {
			if ( ! is_array( $node ) ) continue;
			$node['id'] = self::new_element_id();
			if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$this->regenerate_ids( $node['elements'] );
			}
		}
		unset( $node );
	}

	private static function new_element_id(): string {
		try {
			return bin2hex( random_bytes( 4 ) );
		} catch ( \Throwable $e ) {
			return substr( md5( uniqid( '', true ) ), 0, 8 );
		}
	}
}
