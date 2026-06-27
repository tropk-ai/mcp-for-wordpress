<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\AtomicProps;
use Tropk\Mcp\Elementor\ElementorPage;
/**
 * Generic V4 atomic element insertion. Covers every registered atomic
 * type (free + Pro) without per-widget code:
 *
 * - widget atomics (e-heading, e-button, …) → `elType: "widget"` with
 *   `widgetType` carrying the e-* string.
 * - container atomics (e-div-block, e-flexbox, e-grid) → `elType` IS the
 *   e-* string; they may carry `elements[]` children.
 *
 * Typed settings/styles are normalized through AtomicProps so JSON-string
 * envelopes are healed. Output includes the generated id so the model can
 * follow up with set-atomic-settings / set-element-style etc.
 */
final class ElementorAddAtomicElementAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-add-atomic-element'; }
	protected function meta(): array { return [
		'label'       => __( 'Add a V4 atomic element (widget or container)', 'mcp-for-wordpress' ),
		'description' => __( 'Inserts any registered V4 atomic element on a post. Picks the right node shape (widget vs container) automatically. Settings/styles accept native typed-prop objects OR JSON-string envelopes. Children (elements) are supported for containers and are inserted recursively. Snapshots first.', 'mcp-for-wordpress' ),
		'destructive' => true,
		'idempotent'  => false,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'widgetType' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'widgetType' => [ 'type' => 'string', 'minLength' => 1 ],
			'parent_id'  => [ 'type' => 'string' ],
			'index'      => [ 'type' => 'integer' ],
			'settings'   => [ 'type' => 'object' ],
			'styles'     => [ 'type' => 'object' ],
			'elements'   => [ 'type' => 'array', 'description' => 'Atomic-container children. Each item: { widgetType, settings?, styles?, elements? } recursively.' ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'added'      => [ 'type' => 'boolean' ],
			'element_id' => [ 'type' => [ 'string', 'null' ] ],
			'parent_id'  => [ 'type' => [ 'string', 'null' ] ],
			'widgetType' => [ 'type' => 'string' ],
			'snapshot_id'=> [ 'type' => [ 'string', 'null' ] ],
		],
	]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) )
			&& current_user_can( 'mcp_invoke_destructive_tools' );
	}

	public function execute( array $input = [] ): array {
		$post_id = (int) $input['post_id'];
		$type    = (string) $input['widgetType'];
		if ( ! ElementorPage::is_atomic_widget_type( $type ) ) {
			throw new \RuntimeException( sprintf( 'widgetType "%s" is not an atomic V4 element (must start with e- or a-).', $type ) );
		}
		if ( ! ElementorPage::is_elementor_post( $post_id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $post_id ) );
		}
		[ $registered_widget, $registered_container ] = $this->classify_atomic_type( $type );
		if ( ! $registered_widget && ! $registered_container ) {
			throw new \RuntimeException( sprintf( 'Atomic element type "%s" is not registered on this site.', $type ) );
		}

		$snap     = ( new SnapshotManager() )->snapshot_post( $post_id, 'elementor-add-atomic-element' );
		$node     = $this->build_node(
			$type,
			$registered_container,
			(array) AtomicProps::normalize_value( (array) ( $input['settings'] ?? [] ) ),
			(array) AtomicProps::normalize_value( (array) ( $input['styles']   ?? [] ) ),
			(array) ( $input['elements'] ?? [] )
		);
		$root_id  = (string) $node['id'];

		$raw  = get_post_meta( $post_id, '_elementor_data', true );
		$data = is_string( $raw ) ? (array) ( json_decode( $raw, true ) ?: [] ) : ( is_array( $raw ) ? $raw : [] );

		$parent_in = (string) ( $input['parent_id'] ?? '' );
		$index     = isset( $input['index'] ) ? (int) $input['index'] : -1;
		$inserted  = false;
		$resolved  = null;
		if ( '' !== $parent_in ) {
			$inserted = $this->insert_under( $data, $parent_in, $node, $index );
			$resolved = $inserted ? $parent_in : null;
		} else {
			// No parent: top-level append (atomic elements can live at the top).
			if ( $index >= 0 && $index < count( $data ) ) {
				array_splice( $data, $index, 0, [ $node ] );
			} else {
				$data[] = $node;
			}
			$inserted = true;
		}
		if ( $inserted ) {
			update_post_meta( $post_id, '_elementor_data', wp_slash( (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
			delete_post_meta( $post_id, '_elementor_css' );
		}
		return [
			'added'       => $inserted,
			'element_id'  => $inserted ? $root_id : null,
			'parent_id'   => $resolved,
			'widgetType'  => $type,
			'snapshot_id' => $snap['snapshot_id'] ?? null,
		];
	}

	/**
	 * @return array{0:bool,1:bool} [is_widget, is_container]
	 */
	private function classify_atomic_type( string $type ): array {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return [ false, false ];
		}
		$plugin = \Elementor\Plugin::$instance ?? null;
		if ( ! $plugin ) return [ false, false ];

		$is_widget = false;
		if ( ! empty( $plugin->widgets_manager ) ) {
			$widgets = (array) $plugin->widgets_manager->get_widget_types();
			$is_widget = isset( $widgets[ $type ] );
		}
		$is_container = false;
		if ( ! empty( $plugin->elements_manager ) ) {
			$elements = (array) $plugin->elements_manager->get_element_types();
			if ( isset( $elements[ $type ] ) ) {
				// A widget can also show up in elements_manager — disambiguate
				// via is_container meta on Atomic_Element_Base subclasses.
				$inst = $elements[ $type ];
				$is_container = is_object( $inst ) && method_exists( $inst, 'get_data' )
					? (bool) ( $inst->get_data( 'is_container' ) ?? false )
					: ! $is_widget; // fall back: if it's not registered as a widget, treat it as a container
			}
		}
		return [ $is_widget, $is_container ];
	}

	/**
	 * @param array<string, mixed> $settings
	 * @param array<string, mixed> $styles
	 * @param array<int, mixed>    $children
	 * @return array<string, mixed>
	 */
	private function build_node( string $type, bool $is_container, array $settings, array $styles, array $children ): array {
		$id    = bin2hex( random_bytes( 4 ) );
		$node  = [
			'id'              => $id,
			'elType'          => $is_container ? $type : 'widget',
			'settings'        => $settings ?: new \stdClass(),
			'styles'          => $styles ?: new \stdClass(),
			'editor_settings' => new \stdClass(),
			'version'         => '0.0',
			'elements'        => [],
		];
		if ( ! $is_container ) {
			$node['widgetType'] = $type;
		}
		// Recursively build children (containers only really need them but
		// allow nesting for widgets that grow children in future Pro builds).
		foreach ( $children as $child ) {
			if ( ! is_array( $child ) || empty( $child['widgetType'] ) ) continue;
			$ctype = (string) $child['widgetType'];
			if ( ! ElementorPage::is_atomic_widget_type( $ctype ) ) continue;
			[ , $is_child_container ] = $this->classify_atomic_type( $ctype );
			$node['elements'][] = $this->build_node(
				$ctype,
				$is_child_container,
				(array) AtomicProps::normalize_value( (array) ( $child['settings'] ?? [] ) ),
				(array) AtomicProps::normalize_value( (array) ( $child['styles']   ?? [] ) ),
				(array) ( $child['elements'] ?? [] )
			);
		}
		return $node;
	}

	private function insert_under( array &$nodes, string $parent_id, array $widget, int $index ): bool {
		foreach ( $nodes as &$n ) {
			if ( ! is_array( $n ) ) continue;
			if ( (string) ( $n['id'] ?? '' ) === $parent_id ) {
				if ( ! isset( $n['elements'] ) || ! is_array( $n['elements'] ) ) {
					$n['elements'] = [];
				}
				if ( $index >= 0 && $index < count( $n['elements'] ) ) {
					array_splice( $n['elements'], $index, 0, [ $widget ] );
				} else {
					$n['elements'][] = $widget;
				}
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
}
