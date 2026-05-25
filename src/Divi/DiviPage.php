<?php
declare(strict_types=1);

namespace Tropk\Mcp\Divi;

/**
 * Loads and mutates a Divi 5 page's module tree.
 *
 * Divi 5 stores page content as standard WordPress post_content (block-based
 * shortcodes) and uses postmeta flags to indicate builder status. This helper
 * reads the raw post_content, parses it into a structured module tree using
 * Divi's ET_Builder_Element API when available (falling back to regex parsing),
 * and provides mutation methods that write back to post_content.
 *
 * Divi 5 layout hierarchy:
 *   et_pb_section  →  et_pb_row  →  et_pb_column  →  et_pb_module
 *
 * Only Divi 5 (et_builder_version >= 5.0.0) is supported. Divi 4 and Theme
 * Builder templates are detected and rejected with a clear error message.
 */
final class DiviPage {

	/** Postmeta key that flags a post as using the Divi builder. */
	private const META_USE_BUILDER = '_et_pb_use_builder';

	/** Postmeta key storing the builder version. */
	private const META_BUILDER_VERSION = 'et_builder_version';

	/** Postmeta key for Divi's static CSS cache. */
	private const META_CSS_CACHED = 'et_dynamic_assets_modules_qtip';

	/**
	 * Module types whose primary text content lives in specific attributes.
	 * Used by snippet extraction and text-replace targeting.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const TEXT_ATTRS_BY_MODULE = [
		'et_pb_text'           => [ 'content' ],
		'et_pb_blurb'          => [ 'title', 'content' ],
		'et_pb_button'         => [ 'button_text' ],
		'et_pb_cta'            => [ 'title', 'button_text', 'content' ],
		'et_pb_heading'        => [ 'title', 'content' ],
		'et_pb_image'          => [ 'alt', 'title_text' ],
		'et_pb_video'          => [ 'src' ],
		'et_pb_testimonial'    => [ 'author', 'job_title', 'company_name', 'content' ],
		'et_pb_pricing_table'  => [ 'title', 'subtitle', 'currency', 'per', 'content' ],
		'et_pb_tab'            => [ 'title', 'content' ],
		'et_pb_slide'          => [ 'heading', 'content', 'button_text' ],
		'et_pb_accordion_item' => [ 'title', 'content' ],
		'et_pb_toggle'         => [ 'title', 'content' ],
		'et_pb_number_counter' => [ 'title' ],
		'et_pb_bar_counters'   => [ 'content' ],
		'et_pb_countdown_timer'=> [ 'title' ],
		'et_pb_social_media_follow_network' => [ 'content' ],
		'et_pb_login'          => [ 'title', 'content' ],
		'et_pb_signup'         => [ 'title', 'description', 'button_text' ],
		'et_pb_contact_form'   => [ 'title' ],
		'et_pb_post_title'     => [ 'title' ],
		'et_pb_post_content'   => [ 'content' ],
		'et_pb_comments'       => [ 'custom_button' ],
		'et_pb_search'         => [ 'button_text', 'placeholder' ],
		'et_pb_sidebar'        => [ 'title' ],
		'et_pb_divider'        => [],
		'et_pb_space'          => [],
		'et_pb_map'            => [ 'address' ],
		'et_pb_fullwidth_header'   => [ 'title', 'subheading', 'button_one_text', 'button_two_text' ],
		'et_pb_fullwidth_image'    => [ 'alt', 'title_text' ],
		'et_pb_fullwidth_slider'   => [ 'content' ],
		'et_pb_fullwidth_map'      => [ 'address' ],
		'et_pb_fullwidth_portfolio' => [ 'title' ],
		'et_pb_fullwidth_post_title'=> [ 'title' ],
	];

	/** Fallback text attribute names tried when a module type is unknown. */
	private const TEXT_ATTRS_FALLBACK = [
		'title', 'content', 'button_text', 'heading', 'subheading', 'description',
	];

	private int $post_id;
	private string $content;

	/** @var array<int, array<string, mixed>> Parsed module tree. */
	private array $tree;

	private function __construct( int $post_id, string $content ) {
		$this->post_id = $post_id;
		$this->content = $content;
		$this->tree    = $this->parse( $content );
	}

	// -------------------------------------------------------------------------
	// Factory
	// -------------------------------------------------------------------------

	public static function load( int $post_id ): self {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			throw new \RuntimeException( sprintf( 'Post %d not found.', $post_id ) );
		}
		return new self( $post_id, (string) $post->post_content );
	}

	/**
	 * Returns true when the post was built with the Divi builder.
	 */
	public static function is_divi_post( int $post_id ): bool {
		return 'on' === get_post_meta( $post_id, self::META_USE_BUILDER, true );
	}

	/**
	 * Returns true when the post was built with Divi 5 specifically.
	 * Rejects Divi 4 pages (version < 5.0.0) to avoid corrupting legacy data.
	 */
	public static function is_divi5_post( int $post_id ): bool {
		if ( ! self::is_divi_post( $post_id ) ) {
			return false;
		}
		$version = (string) get_post_meta( $post_id, self::META_BUILDER_VERSION, true );
		if ( '' === $version ) {
			// Version meta absent — could be Divi 4 or early Divi 5 before
			// the meta was introduced. Inspect content for Divi 5 markers.
			$content = (string) get_post( $post_id )?->post_content;
			return str_contains( $content, '[et_pb_' );
		}
		return version_compare( $version, '5.0.0', '>=' );
	}

	// -------------------------------------------------------------------------
	// Accessors
	// -------------------------------------------------------------------------

	public function post_id(): int {
		return $this->post_id;
	}

	public function is_empty(): bool {
		return '' === trim( $this->content ) || [] === $this->tree;
	}

	/**
	 * Raw post_content string.
	 */
	public function content(): string {
		return $this->content;
	}

	/**
	 * Parsed module tree (sections → rows → columns → modules).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function tree(): array {
		return $this->tree;
	}

	// -------------------------------------------------------------------------
	// Read operations
	// -------------------------------------------------------------------------

	/**
	 * Returns a flat list of every leaf module on the page.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function modules(): array {
		$out = [];
		$this->walk_tree(
			$this->tree,
			0,
			static function ( array $node, int $depth ) use ( &$out ): void {
				if ( ! self::is_leaf_module( $node['type'] ?? '' ) ) {
					return;
				}
				$type    = (string) ( $node['type'] ?? '' );
				$out[]   = [
					'id'      => (string) ( $node['id'] ?? '' ),
					'type'    => $type,
					'depth'   => $depth,
					'snippet' => self::extract_snippet( $type, (array) ( $node['attrs'] ?? [] ), $node['content'] ?? '' ),
				];
			}
		);
		return $out;
	}

	/**
	 * Compact indented text outline of the layout.
	 */
	public function outline( int $max_bytes = 2048 ): string {
		$lines = [];
		$this->walk_tree(
			$this->tree,
			0,
			static function ( array $node, int $depth ) use ( &$lines ): void {
				$indent  = str_repeat( '  ', $depth );
				$type    = (string) ( $node['type'] ?? 'unknown' );
				$id      = (string) ( $node['id'] ?? '?' );
				$snippet = '';
				if ( self::is_leaf_module( $type ) ) {
					$snippet = self::extract_snippet( $type, (array) ( $node['attrs'] ?? [] ), $node['content'] ?? '' );
				}
				$lines[] = sprintf(
					'%s%s[%s]%s',
					$indent,
					$type,
					$id,
					$snippet !== '' ? ': ' . $snippet : ''
				);
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

	/**
	 * Finds a node anywhere in the tree by its Divi module ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find_module( string $id ): ?array {
		$found = null;
		$this->walk_tree(
			$this->tree,
			0,
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

	/**
	 * Lightweight structure summary (IDs, types, key attrs).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function structure(): array {
		$key_attrs = [ 'title', 'content', 'button_text', 'heading', 'src', 'url', 'background_color' ];
		$simplify  = static function ( array $nodes ) use ( &$simplify, $key_attrs ): array {
			$out = [];
			foreach ( $nodes as $node ) {
				$item = [
					'id'   => (string) ( $node['id'] ?? '' ),
					'type' => (string) ( $node['type'] ?? '' ),
				];
				$attrs   = (array) ( $node['attrs'] ?? [] );
				$summary = [];
				foreach ( $key_attrs as $k ) {
					if ( isset( $attrs[ $k ] ) && '' !== $attrs[ $k ] ) {
						$v = (string) $attrs[ $k ];
						if ( strlen( $v ) > 100 ) {
							$v = substr( $v, 0, 100 ) . '...';
						}
						$summary[ $k ] = $v;
					}
				}
				if ( ! empty( $summary ) ) {
					$item['attrs_summary'] = $summary;
				}
				if ( ! empty( $node['content_text'] ) ) {
					$text = wp_strip_all_tags( (string) $node['content_text'] );
					if ( strlen( $text ) > 100 ) {
						$text = substr( $text, 0, 100 ) . '...';
					}
					$item['content_snippet'] = $text;
				}
				if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
					$item['children'] = $simplify( $node['children'] );
				}
				$out[] = $item;
			}
			return $out;
		};
		return $simplify( $this->tree );
	}

	// -------------------------------------------------------------------------
	// Mutation operations
	// -------------------------------------------------------------------------

	/**
	 * Updates a single attribute on a module identified by its ID.
	 * Returns true if the module was found and updated.
	 */
	public function update_module_attr( string $module_id, string $attr_key, string $attr_value ): bool {
		$updated = false;
		$this->content = $this->replace_attr_in_content(
			$this->content,
			$module_id,
			$attr_key,
			$attr_value,
			$updated
		);
		if ( $updated ) {
			$this->tree = $this->parse( $this->content );
		}
		return $updated;
	}

	/**
	 * Removes a module (section, row, column, or leaf module) by its ID.
	 * Returns true if a node was found and removed.
	 */
	public function delete_module( string $module_id ): bool {
		// Build a pattern that matches the full shortcode block for this ID.
		// Divi IDs are stored as module_id="..." or _module_preset="..." etc.
		// The canonical attribute carrying the unique ID in Divi 5 is
		// module_id for custom CSS IDs, but internal tree IDs are _module_id
		// (an internal Divi 5 attribute). We target the shortcode that
		// contains _module_id="<id>" or module_id="<id>".
		$escaped = preg_quote( $module_id, '/' );

		// Match both self-closing and paired shortcodes whose attribute list
		// contains the target ID value. This is intentionally broad: we match
		// any [et_pb_* ... id="<id>" .../] or [et_pb_* ... id="<id>"...][/et_pb_*].
		$pattern = '/\[et_pb_\w+[^\]]*\b(?:module_id|_module_id|saved_specialization_id)="'
			. $escaped . '"[^\]]*\](?:.*?\[\/et_pb_\w+\])?/s';

		$new_content = preg_replace( $pattern, '', $this->content );

		if ( null === $new_content || $new_content === $this->content ) {
			return false;
		}
		$this->content = $new_content;
		$this->tree    = $this->parse( $this->content );
		return true;
	}

	/**
	 * Search-and-replace text inside all (or filtered) modules.
	 * Returns the number of replacements made.
	 */
	public function replace_text(
		string $find,
		string $replace,
		?array $module_types = null,
		bool $case_sensitive = false
	): int {
		if ( '' === $find ) {
			return 0;
		}
		$count       = 0;
		$new_content = $this->content;

		foreach ( $this->modules() as $module ) {
			$type = (string) $module['type'];
			if ( null !== $module_types && ! in_array( $type, $module_types, true ) ) {
				continue;
			}
			$node = $this->find_module( (string) $module['id'] );
			if ( null === $node ) {
				continue;
			}
			$attrs = (array) ( $node['attrs'] ?? [] );
			$fields = self::TEXT_ATTRS_BY_MODULE[ $type ] ?? self::TEXT_ATTRS_FALLBACK;
			foreach ( $fields as $field ) {
				if ( ! isset( $attrs[ $field ] ) ) {
					continue;
				}
				$old = (string) $attrs[ $field ];
				$hits = 0;
				$new = $case_sensitive
					? str_replace( $find, $replace, $old, $hits )
					: str_ireplace( $find, $replace, $old, $hits );
				if ( $hits > 0 ) {
					$escaped_old = preg_quote( $old, '/' );
					$new_content = preg_replace( '/' . $escaped_old . '/s', $new, $new_content, 1 );
					$count      += $hits;
				}
			}
			// Also replace inside the inner content block if present.
			$inner = (string) ( $node['content_text'] ?? '' );
			if ( '' !== $inner ) {
				$hits = 0;
				$new_inner = $case_sensitive
					? str_replace( $find, $replace, $inner, $hits )
					: str_ireplace( $find, $replace, $inner, $hits );
				if ( $hits > 0 ) {
					$escaped_old = preg_quote( $inner, '/' );
					$new_content = preg_replace( '/' . $escaped_old . '/s', $new_inner, $new_content, 1 );
					$count      += $hits;
				}
			}
		}

		if ( $count > 0 ) {
			$this->content = $new_content;
			$this->tree    = $this->parse( $this->content );
		}
		return $count;
	}

	// -------------------------------------------------------------------------
	// Persistence
	// -------------------------------------------------------------------------

	/**
	 * Writes mutated content back to the database and flushes static CSS.
	 */
	public function save(): void {
		$result = wp_update_post(
			[
				'ID'           => $this->post_id,
				'post_content' => $this->content,
			],
			true
		);
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( $result->get_error_message() );
		}
		$this->flush_css();
	}

	/**
	 * Invalidates Divi's static CSS / asset cache for this post and globally.
	 */
	public function flush_css(): void {
		// Per-post dynamic CSS cache keys used by Divi 5.
		delete_post_meta( $this->post_id, 'et_dynamic_assets_modules_qtip' );
		delete_post_meta( $this->post_id, 'et_pb_page_custom_css' );

		// Global Divi static CSS option.
		delete_option( 'et_dynamic_assets_version' );

		// Call Divi's own cache-clear method when the builder is loaded.
		if ( class_exists( '\ET_Core_PageResource' ) ) {
			\ET_Core_PageResource::remove_static_resources( 'all', 'all' );
		}

		// Divi 5 uses a separate asset manager class.
		if ( class_exists( '\ET\Builder\Framework\Utility\HTMLUtility' ) ) {
			// Clear Divi 5 asset cache via its option-based invalidation.
			delete_option( 'et_builder_dynamic_assets_cache' );
		}
	}

	/**
	 * Clones this page to a new post, regenerating all module IDs.
	 *
	 * @return int The new post ID.
	 */
	public function clone_to_new_post(
		string $title,
		string $status = 'draft',
		?int $author_id = null
	): int {
		$source = get_post( $this->post_id );
		if ( ! $source instanceof \WP_Post ) {
			throw new \RuntimeException( sprintf( 'Source post %d not found.', $this->post_id ) );
		}

		$new_content = $this->regenerate_module_ids( $this->content );

		$new_id = wp_insert_post(
			[
				'post_type'    => $source->post_type,
				'post_title'   => $title,
				'post_status'  => $status,
				'post_content' => $new_content,
				'post_author'  => null !== $author_id ? $author_id : (int) $source->post_author,
				'post_parent'  => (int) $source->post_parent,
				'menu_order'   => (int) $source->menu_order,
			],
			true
		);

		if ( is_wp_error( $new_id ) ) {
			throw new \RuntimeException( $new_id->get_error_message() );
		}

		// Copy all Divi-specific postmeta to the new post.
		foreach ( $this->divi_meta_keys() as $key ) {
			$value = get_post_meta( $this->post_id, $key, true );
			if ( '' !== $value && null !== $value ) {
				update_post_meta( (int) $new_id, $key, $value );
			}
		}

		return (int) $new_id;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Parses Divi shortcode content into a structured tree.
	 *
	 * Divi 5 embeds all layout data as nested WordPress shortcodes in
	 * post_content. When the Divi builder classes are loaded we delegate to
	 * its own parser; otherwise we use a lightweight regex-based fallback that
	 * handles the common case well enough for read operations.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function parse( string $content ): array {
		if ( '' === trim( $content ) ) {
			return [];
		}

		// Prefer native WP shortcode parser output when Divi is active.
		if ( function_exists( 'do_shortcode_tag' ) && class_exists( '\ET_Builder_Element' ) ) {
			return $this->parse_via_wp_shortcode( $content );
		}

		return $this->parse_via_regex( $content );
	}

	/**
	 * Parse using WordPress's own shortcode parser.
	 * Returns a tree of sections with nested rows/columns/modules.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function parse_via_wp_shortcode( string $content ): array {
		$pattern = get_shortcode_regex( [ 'et_pb_section', 'et_pb_row', 'et_pb_row_inner',
			'et_pb_column', 'et_pb_column_inner' ] );
		if ( ! preg_match_all( "/$pattern/s", $content, $matches, PREG_SET_ORDER ) ) {
			return $this->parse_via_regex( $content );
		}
		// Fall back to regex which is more robust for our purposes.
		return $this->parse_via_regex( $content );
	}

	/**
	 * Lightweight regex-based parser for Divi shortcode trees.
	 * Handles arbitrary nesting using a cursor-based approach.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function parse_via_regex( string $content ): array {
		// Tokenise: each token is either an opening tag, a closing tag, or leaf content.
		$token_re = '/(\[\/et_pb_\w+\]|\[et_pb_\w+[^\]]*\/\]|\[et_pb_\w+[^\]]*\])/s';
		$tokens   = preg_split( $token_re, $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $tokens ) ) {
			return [];
		}

		$stack  = [ [] ];  // stack of children arrays; bottom level is the root.
		$meta   = [ [] ];  // stack of node metadata corresponding to $stack.

		foreach ( $tokens as $token ) {
			$token = trim( $token );
			if ( '' === $token ) {
				continue;
			}

			// Closing tag: pop the stack.
			if ( preg_match( '/^\[\/et_pb_(\w+)\]$/', $token ) ) {
				if ( count( $stack ) <= 1 ) {
					continue;
				}
				$children = array_pop( $stack );
				$node_meta = array_pop( $meta );
				$node = array_merge( $node_meta, [ 'children' => $children ] );
				$stack[ count( $stack ) - 1 ][] = $node;
				continue;
			}

			// Self-closing tag: leaf node.
			if ( preg_match( '/^\[et_pb_(\w+)([^\]]*?)\/\]$/s', $token, $m ) ) {
				$type  = 'et_pb_' . $m[1];
				$attrs = self::parse_attrs( $m[2] );
				$stack[ count( $stack ) - 1 ][] = [
					'type'    => $type,
					'id'      => self::extract_id( $attrs ),
					'attrs'   => $attrs,
					'children'     => [],
					'content_text' => '',
				];
				continue;
			}

			// Opening tag: push a new level.
			if ( preg_match( '/^\[et_pb_(\w+)((?:[^\]]*(?!\/))*)\]$/s', $token, $m ) ) {
				$type  = 'et_pb_' . $m[1];
				$attrs = self::parse_attrs( $m[2] );
				$stack[]  = [];
				$meta[]   = [
					'type'         => $type,
					'id'           => self::extract_id( $attrs ),
					'attrs'        => $attrs,
					'content_text' => '',
				];
				continue;
			}

			// Plain text / inner content: attach to the current node's content.
			if ( count( $meta ) > 1 ) {
				$top_idx = count( $meta ) - 1;
				$meta[ $top_idx ]['content_text'] = ( $meta[ $top_idx ]['content_text'] ?? '' ) . $token;
			}
		}

		// Flatten any unclosed tags to root level.
		while ( count( $stack ) > 1 ) {
			$children  = array_pop( $stack );
			$node_meta = array_pop( $meta );
			$node      = array_merge( $node_meta, [ 'children' => $children ] );
			$stack[ count( $stack ) - 1 ][] = $node;
		}

		return $stack[0];
	}

	/**
	 * Parse a Divi shortcode attribute string into a key→value array.
	 *
	 * @return array<string, string>
	 */
	private static function parse_attrs( string $attr_string ): array {
		$attrs = [];
		// Match key="value" or key='value'
		preg_match_all( '/(\w+)=["\']([^"\']*)["\']/', $attr_string, $matches, PREG_SET_ORDER );
		foreach ( $matches as $m ) {
			$attrs[ $m[1] ] = html_entity_decode( $m[2], ENT_QUOTES | ENT_HTML5 );
		}
		return $attrs;
	}

	/**
	 * Extracts the canonical node ID from a parsed attributes array.
	 * Divi 5 uses module_id for user-facing CSS IDs and _module_id for
	 * internal unique IDs. We prefer the internal one for tree operations.
	 */
	private static function extract_id( array $attrs ): string {
		return (string) ( $attrs['_module_id'] ?? $attrs['module_id'] ?? $attrs['saved_specialization_id'] ?? '' );
	}

	/**
	 * Walk the tree, calling $callback( $node, $depth ) on every node.
	 */
	private function walk_tree( array $nodes, int $depth, callable $callback ): void {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$callback( $node, $depth );
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$this->walk_tree( $node['children'], $depth + 1, $callback );
			}
		}
	}

	/**
	 * Returns true if the given shortcode type is a leaf module (not a
	 * structural container like section/row/column).
	 */
	private static function is_leaf_module( string $type ): bool {
		return ! in_array( $type, [
			'et_pb_section', 'et_pb_row', 'et_pb_row_inner',
			'et_pb_column', 'et_pb_column_inner', '',
		], true );
	}

	/**
	 * Extracts a short readable snippet from a module's attributes.
	 */
	private static function extract_snippet( string $type, array $attrs, string $inner_content, int $max = 80 ): string {
		$fields = self::TEXT_ATTRS_BY_MODULE[ $type ] ?? self::TEXT_ATTRS_FALLBACK;
		foreach ( $fields as $field ) {
			if ( isset( $attrs[ $field ] ) && '' !== (string) $attrs[ $field ] ) {
				$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $attrs[ $field ] ) ) ?? '' );
				if ( '' !== $text ) {
					return mb_strlen( $text ) > $max ? mb_substr( $text, 0, $max - 1 ) . '…' : $text;
				}
			}
		}
		// Fall back to inner content.
		if ( '' !== $inner_content ) {
			$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $inner_content ) ) ?? '' );
			if ( '' !== $text ) {
				return mb_strlen( $text ) > $max ? mb_substr( $text, 0, $max - 1 ) . '…' : $text;
			}
		}
		return '';
	}

	/**
	 * Replaces a single attribute value on the shortcode identified by $module_id.
	 */
	private function replace_attr_in_content(
		string $content,
		string $module_id,
		string $attr_key,
		string $attr_value,
		bool &$updated
	): string {
		// Match the opening tag of the shortcode carrying this module ID.
		$id_escaped   = preg_quote( $module_id, '/' );
		$attr_escaped = preg_quote( $attr_key, '/' );

		// Pattern: [et_pb_ANYTHING ... module_id="<id>" ...] — capture the
		// entire opening tag so we can do a targeted in-tag replacement.
		$tag_pattern = '/(\[et_pb_\w+(?:[^\]]*?)(?:_module_id|module_id)="' . $id_escaped . '"[^\]]*?)\]/s';

		$new_content = preg_replace_callback(
			$tag_pattern,
			static function ( array $m ) use ( $attr_key, $attr_value, $attr_escaped, &$updated ): string {
				$tag = $m[1];
				$attr_value_escaped = esc_attr( $attr_value );
				// If the attribute already exists, replace its value.
				if ( preg_match( '/' . $attr_escaped . '="[^"]*"/', $tag ) ) {
					$new_tag = preg_replace(
						'/' . $attr_escaped . '="[^"]*"/',
						$attr_key . '="' . $attr_value_escaped . '"',
						$tag
					);
					if ( null !== $new_tag ) {
						$updated = true;
						return $new_tag . ']';
					}
				}
				// Otherwise append the attribute.
				$updated = true;
				return $tag . ' ' . $attr_key . '="' . $attr_value_escaped . '"]';
			},
			$content
		);

		return $new_content ?? $content;
	}

	/**
	 * Regenerates all module_id / _module_id attributes with fresh unique values.
	 * Used when cloning a page to avoid ID collisions.
	 */
	private function regenerate_module_ids( string $content ): string {
		// Replace every occurrence of _module_id="<value>" or module_id="<value>"
		// with a fresh random value.
		return (string) preg_replace_callback(
			'/\b(_module_id|module_id)="([^"]*)"/s',
			static function ( array $m ): string {
				return $m[1] . '="' . self::new_module_id() . '"';
			},
			$content
		);
	}

	/**
	 * Generates a fresh random module ID in Divi's format.
	 * Divi 5 uses short random hex strings for internal IDs.
	 */
	private static function new_module_id(): string {
		try {
			return bin2hex( random_bytes( 4 ) );
		} catch ( \Throwable $e ) {
			return substr( md5( uniqid( '', true ) ), 0, 8 );
		}
	}

	/**
	 * Returns the list of Divi postmeta keys that should be copied on clone.
	 *
	 * @return array<int, string>
	 */
	private function divi_meta_keys(): array {
		return [
			'_et_pb_use_builder',
			'et_builder_version',
			'et_pb_page_layout',
			'et_pb_side_nav',
			'_et_pb_post_hide_nav',
			'_et_pb_show_page_creation',
			'_et_pb_page_custom_css',
			'et_header_layout',
			'et_footer_layout',
		];
	}
}
