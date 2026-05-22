<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\WooCommerce;
use Tropk\Mcp\Abilities\AbstractAbility;
final class WooStatusAbility extends AbstractAbility {
	public function slug(): string { return 'woo-status'; }
	protected function meta(): array { return [ 'label' => __( 'Check WooCommerce status', 'mcp-for-wordpress' ), 'description' => __( 'Returns whether WooCommerce is active + version + currency + product/order count.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'active' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		if ( ! class_exists( 'WooCommerce' ) ) return [ 'active' => false ];
		$products = wp_count_posts( 'product' );
		$orders   = wp_count_posts( 'shop_order' );
		return [
			'active'   => true,
			'version'  => defined( 'WC_VERSION' ) ? (string) WC_VERSION : '',
			'currency' => function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '',
			'products' => (int) ( $products->publish ?? 0 ),
			'orders'   => (int) array_sum( (array) $orders ),
		];
	}
}
