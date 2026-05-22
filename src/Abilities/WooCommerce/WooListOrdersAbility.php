<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\WooCommerce;
use Tropk\Mcp\Abilities\AbstractAbility;
final class WooListOrdersAbility extends AbstractAbility {
	public function slug(): string { return 'woo-list-orders'; }
	protected function meta(): array { return [ 'label' => __( 'List WooCommerce orders', 'mcp-for-wordpress' ), 'description' => __( 'Returns recent orders with id, status, total, customer.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => [ 'limit' => [ 'type' => 'integer', 'default' => 30 ], 'status' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'orders' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) return [ 'orders' => [] ];
		$args = [ 'limit' => (int) ( $input['limit'] ?? 30 ), 'orderby' => 'date', 'order' => 'DESC' ];
		if ( ! empty( $input['status'] ) ) $args['status'] = (string) $input['status'];
		$orders = wc_get_orders( $args );
		$out = [];
		foreach ( (array) $orders as $o ) {
			$out[] = [ 'id' => (int) $o->get_id(), 'status' => (string) $o->get_status(), 'total' => (string) $o->get_total(), 'currency' => (string) $o->get_currency(), 'customer_email' => (string) $o->get_billing_email() ];
		}
		return [ 'orders' => $out ];
	}
}
