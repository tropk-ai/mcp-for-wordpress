<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorRuntime;
final class ElementorListGlobalClassesAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-global-classes'; }
	protected function meta(): array { return [
		'label'       => __( 'List Elementor V4 global classes', 'mcp-for-wordpress' ),
		'description' => __( 'Returns the V4 Global Classes registered on the active Kit: id, label and the order array as Elementor renders them. Use channel=preview to read the unpublished draft.', 'mcp-for-wordpress' ),
		'readonly'    => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties' => [
			'channel' => [ 'type' => 'string', 'enum' => [ 'frontend', 'preview' ], 'default' => 'frontend' ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'classes' => [ 'type' => 'array' ],
			'order'   => [ 'type' => 'array' ],
			'count'   => [ 'type' => 'integer' ],
		],
	]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$repo = ElementorRuntime::require_global_classes();
		$channel = (string) ( $input['channel'] ?? 'frontend' );
		if ( 'preview' === $channel && method_exists( $repo, 'set_context' ) ) {
			// The repository selects context via its constructor / set_context;
			// fall back to frontend if the API is not present.
			try { $repo->set_context( 'preview' ); } catch ( \Throwable $e ) {}
		}
		$collection = $repo->all();
		$items = method_exists( $collection, 'get_items' ) ? (array) $collection->get_items() : [];
		$order = method_exists( $collection, 'get_order' ) ? (array) $collection->get_order() : (array) $repo->get_order();
		$out = [];
		foreach ( $items as $id => $item ) {
			$out[] = [
				'id'    => (string) $id,
				'label' => isset( $item['label'] ) ? (string) $item['label'] : '',
				'data'  => is_array( $item ) ? $item : [],
			];
		}
		return [ 'classes' => $out, 'order' => array_values( $order ), 'count' => count( $out ) ];
	}
}
