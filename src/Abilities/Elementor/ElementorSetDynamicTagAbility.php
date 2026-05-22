<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorSetDynamicTagAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-set-dynamic-tag'; }
	protected function meta(): array { return [ 'label' => __( 'Set a dynamic tag on a widget setting', 'mcp-for-wordpress' ), 'description' => __( 'Binds an Elementor dynamic tag (e.g. post-title, site-title) to a specific setting key on an element by writing the [elementor-tag …] token into the element\'s settings.__dynamic__ map. Snapshots the post first.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id', 'element_id', 'setting_key', 'tag_name' ], 'properties' => [
		'post_id'     => [ 'type' => 'integer', 'minimum' => 1 ],
		'element_id'  => [ 'type' => 'string', 'minLength' => 1 ],
		'setting_key' => [ 'type' => 'string', 'minLength' => 1 ],
		'tag_name'    => [ 'type' => 'string', 'minLength' => 1 ],
		'tag_settings' => [ 'type' => 'object' ],
	] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'snapshot_id' => [ 'type' => [ 'string', 'null' ] ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$post_id = (int) $input['post_id'];
		$element_id = (string) $input['element_id'];
		$setting_key = (string) $input['setting_key'];
		$tag_name = (string) $input['tag_name'];
		$tag_settings = isset( $input['tag_settings'] ) && is_array( $input['tag_settings'] ) ? $input['tag_settings'] : [];
		$page = ElementorPage::load( $post_id );
		if ( null === $page->find_widget( $element_id ) ) {
			throw new \RuntimeException( sprintf( 'Element "%s" not found.', $element_id ) );
		}
		$snap = ( new SnapshotManager() )->snapshot_post( $post_id, 'elementor-set-dynamic-tag' );
		$tag_id = wp_rand( 1000000, 9999999 );
		$encoded = '[elementor-tag id="' . $tag_id . '" name="' . $tag_name . '" settings="' . urlencode( (string) wp_json_encode( $tag_settings, JSON_FORCE_OBJECT ) ) . '"]';
		$ref = new \ReflectionClass( $page );
		$prop = $ref->getProperty( 'data' );
		$prop->setAccessible( true );
		$data = (array) $prop->getValue( $page );
		$this->apply( $data, $element_id, $setting_key, $encoded );
		$prop->setValue( $page, $data );
		$page->save();
		return [ 'updated' => true, 'post_id' => $post_id, 'element_id' => $element_id, 'snapshot_id' => $snap['snapshot_id'] ];
	}
	private function apply( array &$nodes, string $target, string $key, string $encoded ): void {
		foreach ( $nodes as &$node ) {
			if ( ! is_array( $node ) ) continue;
			if ( ( $node['id'] ?? '' ) === $target ) {
				if ( ! isset( $node['settings'] ) || ! is_array( $node['settings'] ) ) $node['settings'] = [];
				$dynamic = isset( $node['settings']['__dynamic__'] ) && is_array( $node['settings']['__dynamic__'] ) ? $node['settings']['__dynamic__'] : [];
				$dynamic[ $key ] = $encoded;
				$node['settings']['__dynamic__'] = $dynamic;
				return;
			}
			if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$this->apply( $node['elements'], $target, $key, $encoded );
			}
		}
		unset( $node );
	}
}
