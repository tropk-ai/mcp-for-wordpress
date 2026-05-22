<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\WooCommerce;
use Tropk\Mcp\Abilities\AbstractAbility;
final class WooListProductsAbility extends AbstractAbility {
	public function slug(): string { return 'woo-list-products'; }
	protected function meta(): array { return [ 'label' => __( 'List WooCommerce products', 'mcp-for-wordpress' ), 'description' => __( 'Returns products with id, sku, price, stock_status. Requires WooCommerce.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => [ 'limit' => [ 'type' => 'integer', 'default' => 50 ], 'search' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'products' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_products' ) || current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		if ( ! class_exists( 'WooCommerce' ) ) return [ 'products' => [] ];
		$args = [ 'post_type' => 'product', 'posts_per_page' => (int) ( $input['limit'] ?? 50 ) ];
		if ( ! empty( $input['search'] ) ) $args['s'] = (string) $input['search'];
		$q = new \WP_Query( $args );
		$out = [];
		foreach ( $q->posts as $p ) {
			$out[] = [
				'id' => (int) $p->ID,
				'name' => (string) $p->post_title,
				'sku' => (string) get_post_meta( $p->ID, '_sku', true ),
				'price' => (string) get_post_meta( $p->ID, '_price', true ),
				'stock_status' => (string) get_post_meta( $p->ID, '_stock_status', true ),
			];
		}
		return [ 'products' => $out ];
	}
}
