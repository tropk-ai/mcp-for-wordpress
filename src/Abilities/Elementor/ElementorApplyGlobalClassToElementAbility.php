<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\AtomicProps;
use Tropk\Mcp\Elementor\ElementorPage;
use Tropk\Mcp\Elementor\ElementorRuntime;
/**
 * Push a V4 global class id onto an element's `classes` typed prop.
 *
 * Atomic V4 widgets reference both local and global classes through the
 * same `{"$$type":"classes","value":[…]}` prop on the element's settings.
 * Adding/removing a global class is therefore a structured edit on that
 * single array — no separate Repository call required, the class itself
 * already exists in the Kit.
 */
final class ElementorApplyGlobalClassToElementAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-apply-global-class-to-element'; }
	protected function meta(): array { return [
		'label'       => __( "Apply / unapply an Elementor V4 global class to an element", 'mcp-for-wordpress' ),
		'description' => __( "Adds (default) or removes a V4 Global Class id from an atomic element's `classes` prop. Snapshots the post first.", 'mcp-for-wordpress' ),
		'destructive' => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'element_id', 'class_id' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'element_id' => [ 'type' => 'string', 'minLength' => 1 ],
			'class_id'   => [ 'type' => 'string', 'minLength' => 1 ],
			'action'     => [ 'type' => 'string', 'enum' => [ 'add', 'remove' ], 'default' => 'add' ],
			'verify_class_exists' => [ 'type' => 'boolean', 'default' => true ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'updated'     => [ 'type' => 'boolean' ],
			'classes'     => [ 'type' => 'array' ],
			'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
		],
	]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) )
			&& current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$id     = (int) $input['post_id'];
		$class  = (string) $input['class_id'];
		$action = (string) ( $input['action'] ?? 'add' );
		if ( ! ElementorPage::is_elementor_post( $id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $id ) );
		}
		if ( ! empty( $input['verify_class_exists'] ?? true ) && 'add' === $action ) {
			$repo = ElementorRuntime::require_global_classes();
			if ( ! $repo->get( $class ) ) {
				throw new \RuntimeException( sprintf( 'Global class "%s" does not exist on the active Kit.', $class ) );
			}
		}
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-apply-global-class' );
		$page = ElementorPage::load( $id );

		$next   = null;
		$ok     = false;
		$cb     = function ( array &$node ) use ( $input, $class, $action, &$next, &$ok ): void {
			if ( ( $node['id'] ?? '' ) !== (string) $input['element_id'] ) {
				return;
			}
			$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
			$current  = [];
			if ( isset( $settings['classes'] ) && AtomicProps::is_envelope( $settings['classes'] ) ) {
				$current = (array) $settings['classes']['value'];
			}
			if ( 'add' === $action ) {
				$current = array_values( array_unique( array_merge( $current, [ $class ] ) ) );
			} else {
				$current = array_values( array_filter( $current, static fn( $v ): bool => (string) $v !== $class ) );
			}
			$settings['classes'] = AtomicProps::classes( $current );
			$node['settings']    = $settings;
			$next                = $current;
			$ok                  = true;
		};
		$page->walk_for_update( $cb );
		if ( $ok ) {
			$page->save();
		}
		return [ 'updated' => $ok, 'classes' => (array) $next, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
