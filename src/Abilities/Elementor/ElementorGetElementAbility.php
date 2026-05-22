<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorGetElementAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-element'; }
	protected function meta(): array { return [
		'label' => __( 'Get an Elementor element', 'mcp-for-wordpress' ),
		'description' => __( 'Retrieves a single Elementor element (container or widget) by element ID and includes the path of ancestor IDs from the root.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'element_id' ],
		'properties'           => [
			'post_id'    => [ 'type' => 'integer', 'minimum' => 1 ],
			'element_id' => [ 'type' => 'string', 'minLength' => 1 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'found' => [ 'type' => 'boolean' ], 'element' => [ 'type' => [ 'object', 'null' ] ],
		'path' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$target = (string) $input['element_id'];
		$found  = null;
		$path   = [];
		$walk   = function ( array $nodes, array $stack ) use ( &$walk, $target, &$found, &$path ): bool {
			foreach ( $nodes as $node ) {
				if ( ! is_array( $node ) ) continue;
				$id    = (string) ( $node['id'] ?? '' );
				$next  = $stack;
				if ( '' !== $id ) $next[] = $id;
				if ( $id === $target ) {
					$found = $node;
					$path  = $next;
					return true;
				}
				if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
					if ( $walk( $node['elements'], $next ) ) return true;
				}
			}
			return false;
		};
		$walk( $page->data(), [] );
		return [ 'found' => null !== $found, 'element' => $found, 'path' => $path ];
	}
}
