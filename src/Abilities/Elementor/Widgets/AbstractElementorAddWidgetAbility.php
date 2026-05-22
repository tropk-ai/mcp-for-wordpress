<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

/**
 * Base class for the per-widget-type "add widget" shorthands.
 *
 * Subclasses declare the widget type, a human label, and the default
 * settings shape. Everything else (snapshot, schema, tree walk,
 * insertion, save + css flush, atomic guard) lives here so each
 * concrete subclass stays ~10 lines.
 *
 * If no parent_id is supplied the widget is inserted into the last
 * top-level container, or — if the page has no containers yet — a
 * brand-new container is created automatically so the call still
 * succeeds. This mirrors how operators expect "add a heading" to work
 * without first having to call elementor-add-container.
 */
abstract class AbstractElementorAddWidgetAbility extends AbstractAbility {

	/** Elementor widgetType identifier, e.g. 'heading', 'image', 'e-heading'. */
	abstract protected function widget_type(): string;

	/** Default settings merged with caller-supplied settings (caller wins). */
	abstract protected function default_settings(): array;

	/** Human-readable label, e.g. 'heading', 'image carousel'. */
	abstract protected function widget_label(): string;

	public function slug(): string {
		// e.g. heading -> elementor-add-heading, text-editor -> elementor-add-text-editor,
		// e-heading -> elementor-add-e-heading, a-button -> elementor-add-a-button.
		return 'elementor-add-' . $this->widget_type();
	}

	protected function meta(): array {
		$label = $this->widget_label();
		return [
			'label'       => sprintf(
				/* translators: %s: widget type label, e.g. "heading" */
				__( 'Add an Elementor %s widget', 'mcp-for-wordpress' ),
				$label
			),
			'description' => sprintf(
				/* translators: %1$s widget label, %2$s widget type slug */
				__( 'Inserts an Elementor %1$s widget (widgetType "%2$s") into the page. If parent_id is omitted the widget is appended to the last top-level container, or a fresh container is created when the page has none.', 'mcp-for-wordpress' ),
				$label,
				$this->widget_type()
			),
			'destructive' => true,
		];
	}

	protected function input_schema(): array {
		return [
			'additionalProperties' => false,
			'required'             => [ 'post_id' ],
			'properties'           => [
				'post_id'   => [ 'type' => 'integer', 'description' => 'Target post/page ID.' ],
				'parent_id' => [ 'type' => 'string', 'description' => 'Optional container ID to insert into. Omit to append to the last top-level container.' ],
				'settings'  => [ 'type' => 'object', 'description' => 'Widget settings; merged on top of the default settings shipped with this ability.' ],
				'index'     => [ 'type' => 'integer', 'description' => 'Optional zero-based position within the parent. -1 or omitted appends.' ],
			],
		];
	}

	protected function output_schema(): array {
		return [
			'properties' => [
				'added'         => [ 'type' => 'boolean' ],
				'widget_id'     => [ 'type' => [ 'string', 'null' ] ],
				'parent_id'     => [ 'type' => [ 'string', 'null' ] ],
				'widget_type'   => [ 'type' => 'string' ],
				'snapshot_id'   => [ 'type' => [ 'string', 'null' ] ],
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) )
			&& current_user_can( 'mcp_invoke_destructive_tools' );
	}

	public function execute( array $input = [] ): array {
		$post_id = (int) ( $input['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			throw new \RuntimeException( 'post_id is required.' );
		}
		if ( ! ElementorPage::is_elementor_post( $post_id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $post_id ) );
		}

		$widget_type = $this->widget_type();
		$snap        = ( new SnapshotManager() )->snapshot_post( $post_id, 'elementor-add-' . $widget_type );

		$raw  = get_post_meta( $post_id, '_elementor_data', true );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : [] );
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		// Merge caller settings on top of defaults.
		$caller_settings   = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : [];
		$merged_settings   = $this->merge_settings( $this->default_settings(), $caller_settings );
		$new_id            = bin2hex( random_bytes( 4 ) );
		$widget_node       = $this->build_widget_node( $new_id, $widget_type, $merged_settings );
		$parent_id_in      = isset( $input['parent_id'] ) ? (string) $input['parent_id'] : '';
		$index             = isset( $input['index'] ) ? (int) $input['index'] : -1;
		$resolved_parent   = null;
		$inserted          = false;

		if ( '' !== $parent_id_in ) {
			$inserted = $this->insert_under( $data, $parent_id_in, $widget_node, $index );
			if ( $inserted ) {
				$resolved_parent = $parent_id_in;
			} else {
				throw new \RuntimeException( sprintf( 'Parent container %s was not found.', $parent_id_in ) );
			}
		} else {
			// No parent: append to the LAST top-level container, or create one.
			$last_container_index = null;
			for ( $i = count( $data ) - 1; $i >= 0; $i-- ) {
				$el_type = (string) ( $data[ $i ]['elType'] ?? '' );
				if ( 'container' === $el_type || 'section' === $el_type ) {
					$last_container_index = $i;
					break;
				}
			}
			if ( null === $last_container_index ) {
				$container_id = bin2hex( random_bytes( 4 ) );
				$data[] = [
					'id'       => $container_id,
					'elType'   => 'container',
					'settings' => new \stdClass(),
					'elements' => [ $widget_node ],
					'isInner'  => false,
				];
				$resolved_parent = $container_id;
				$inserted        = true;
			} else {
				if ( ! isset( $data[ $last_container_index ]['elements'] ) || ! is_array( $data[ $last_container_index ]['elements'] ) ) {
					$data[ $last_container_index ]['elements'] = [];
				}
				$this->splice_into( $data[ $last_container_index ]['elements'], $widget_node, $index );
				$resolved_parent = (string) ( $data[ $last_container_index ]['id'] ?? '' );
				$inserted        = true;
			}
		}

		if ( $inserted ) {
			update_post_meta(
				$post_id,
				'_elementor_data',
				wp_slash( (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) )
			);
			delete_post_meta( $post_id, '_elementor_css' );
		}

		return [
			'added'       => $inserted,
			'widget_id'   => $inserted ? $new_id : null,
			'parent_id'   => $resolved_parent,
			'widget_type' => $widget_type,
			'snapshot_id' => $snap['snapshot_id'] ?? null,
		];
	}

	/**
	 * Build the JSON node for the widget. Atomic widgets (a- and e- prefixed)
	 * use a slightly different shape — they wrap controls under a 'controls'
	 * or 'props' key in some Elementor V4 builds. We emit the most
	 * compatible shape and let Elementor normalise on load.
	 */
	protected function build_widget_node( string $id, string $widget_type, array $settings ): array {
		$node = [
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => $widget_type,
			'settings'   => $settings ?: new \stdClass(),
			'elements'   => [],
		];
		if ( ElementorPage::is_atomic_widget_type( $widget_type ) ) {
			// Atomic widgets ship as 'editor_settings' on some V4 builds; keep
			// 'settings' too so non-atomic readers still see something.
			$node['editor_settings'] = $settings ?: new \stdClass();
			$node['version']         = '0.0';
		}
		return $node;
	}

	/** Recursive caller-wins merge so nested setting groups (e.g. typography) blend correctly. */
	protected function merge_settings( array $defaults, array $overrides ): array {
		foreach ( $overrides as $k => $v ) {
			if ( is_array( $v ) && isset( $defaults[ $k ] ) && is_array( $defaults[ $k ] ) ) {
				$defaults[ $k ] = $this->merge_settings( $defaults[ $k ], $v );
			} else {
				$defaults[ $k ] = $v;
			}
		}
		return $defaults;
	}

	/** Walk the tree and splice the widget under the parent with id $parent_id. */
	protected function insert_under( array &$nodes, string $parent_id, array $widget, int $index ): bool {
		foreach ( $nodes as &$n ) {
			if ( ( $n['id'] ?? '' ) === $parent_id ) {
				if ( ! isset( $n['elements'] ) || ! is_array( $n['elements'] ) ) {
					$n['elements'] = [];
				}
				$this->splice_into( $n['elements'], $widget, $index );
				return true;
			}
			if ( isset( $n['elements'] ) && is_array( $n['elements'] ) ) {
				if ( $this->insert_under( $n['elements'], $parent_id, $widget, $index ) ) {
					return true;
				}
			}
		}
		unset( $n );
		return false;
	}

	protected function splice_into( array &$bucket, array $widget, int $index ): void {
		if ( $index < 0 || $index >= count( $bucket ) ) {
			$bucket[] = $widget;
			return;
		}
		array_splice( $bucket, $index, 0, [ $widget ] );
	}
}
