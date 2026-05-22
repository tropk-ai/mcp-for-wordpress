<?php
/**
 * WooCommerce abilities for the Abilities API.
 *
 * Registers ~15 abilities under the `wc/*` namespace covering products,
 * orders, customers, stock, coupons, gateways, shipping zones and a few
 * reports. All ability callbacks short-circuit safely when WooCommerce
 * is not active.
 *
 * Inspired by the tool catalog of RaheesAhmed/wordpress-mcp-server (MIT),
 * reimplemented here on top of WooCommerce's PHP API.
 *
 * @package Tropk\Mcp\Extras
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'tropk_mcp_wc_is_active' ) ) {
	function tropk_mcp_wc_is_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	function tropk_mcp_wc_require_active(): void {
		if ( ! tropk_mcp_wc_is_active() ) {
			throw new \RuntimeException( 'WooCommerce is not active on this site.' );
		}
	}

	function tropk_mcp_wc_can_edit_shop(): bool {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}
}

add_action(
	'wp_abilities_api_init',
	static function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'tropk-wc/is-active',
			[
				'label'               => 'WooCommerce: is active',
     'category'            => 'tropk-core',
				'description'         => 'Returns whether WooCommerce is loaded and basic info about the install.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'active'  => [ 'type' => 'boolean' ],
						'version' => [ 'type' => [ 'string', 'null' ] ],
					],
				],
				'execute_callback'    => static function (): array {
					return [
						'active'  => tropk_mcp_wc_is_active(),
						'version' => defined( 'WC_VERSION' ) ? WC_VERSION : null,
					];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_posts' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/get-products',
			[
				'label'               => 'WooCommerce: list products',
     'category'            => 'tropk-core',
				'description'         => 'List products with filtering.',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
						'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
						'status'   => [ 'type' => 'string', 'enum' => [ 'any', 'publish', 'draft', 'pending', 'private' ], 'default' => 'any' ],
						'search'   => [ 'type' => 'string' ],
						'category' => [ 'type' => 'string', 'description' => 'Category slug.' ],
						'sku'      => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$args = [
						'limit'   => (int) ( $input['per_page'] ?? 20 ),
						'page'    => (int) ( $input['page'] ?? 1 ),
						'status'  => (string) ( $input['status'] ?? 'any' ),
						'return'  => 'objects',
						'orderby' => 'date',
						'order'   => 'DESC',
					];
					if ( ! empty( $input['search'] ) ) {
						$args['s'] = (string) $input['search'];
					}
					if ( ! empty( $input['category'] ) ) {
						$args['category'] = [ (string) $input['category'] ];
					}
					if ( ! empty( $input['sku'] ) ) {
						$args['sku'] = (string) $input['sku'];
					}
					$products = wc_get_products( $args );
					$out      = [];
					foreach ( $products as $p ) {
						$out[] = [
							'id'            => $p->get_id(),
							'name'          => $p->get_name(),
							'sku'           => $p->get_sku(),
							'price'         => $p->get_price(),
							'regular_price' => $p->get_regular_price(),
							'sale_price'    => $p->get_sale_price(),
							'stock_status'  => $p->get_stock_status(),
							'stock_qty'     => $p->get_stock_quantity(),
							'status'        => $p->get_status(),
							'type'          => $p->get_type(),
							'permalink'     => $p->get_permalink(),
						];
					}
					return [ 'products' => $out, 'count' => count( $out ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_products' ) || tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/get-product',
			[
				'label'               => 'WooCommerce: get product',
     'category'            => 'tropk-core',
				'description'         => 'Get a single product by ID.',
				'input_schema'        => [ 'type' => 'object', 'required' => [ 'id' ], 'properties' => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$product = wc_get_product( (int) $input['id'] );
					if ( ! $product ) {
						throw new \RuntimeException( sprintf( 'Product %d not found.', (int) $input['id'] ) );
					}
					return [ 'product' => $product->get_data() ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_products' ) || tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/create-product',
			[
				'label'               => 'WooCommerce: create product',
     'category'            => 'tropk-core',
				'description'         => 'Create a simple, variable, grouped or external product.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'name' ],
					'properties' => [
						'name'          => [ 'type' => 'string', 'minLength' => 1 ],
						'type'          => [ 'type' => 'string', 'enum' => [ 'simple', 'variable', 'grouped', 'external' ], 'default' => 'simple' ],
						'sku'           => [ 'type' => 'string' ],
						'regular_price' => [ 'type' => 'string' ],
						'sale_price'    => [ 'type' => 'string' ],
						'description'   => [ 'type' => 'string' ],
						'short_description' => [ 'type' => 'string' ],
						'status'        => [ 'type' => 'string', 'enum' => [ 'draft', 'publish', 'pending' ], 'default' => 'draft' ],
						'stock_qty'     => [ 'type' => 'integer' ],
						'manage_stock'  => [ 'type' => 'boolean', 'default' => false ],
						'categories'    => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
						'tags'          => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
						'images'        => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Attachment IDs.' ],
						'featured'      => [ 'type' => 'boolean' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$type    = (string) ( $input['type'] ?? 'simple' );
					$class   = '\\WC_Product_' . ucfirst( $type );
					$product = class_exists( $class ) ? new $class() : new \WC_Product();
					$product->set_name( (string) $input['name'] );
					$product->set_status( (string) ( $input['status'] ?? 'draft' ) );
					if ( isset( $input['sku'] ) ) {
						$product->set_sku( (string) $input['sku'] );
					}
					if ( isset( $input['regular_price'] ) ) {
						$product->set_regular_price( (string) $input['regular_price'] );
					}
					if ( isset( $input['sale_price'] ) ) {
						$product->set_sale_price( (string) $input['sale_price'] );
					}
					if ( isset( $input['description'] ) ) {
						$product->set_description( (string) $input['description'] );
					}
					if ( isset( $input['short_description'] ) ) {
						$product->set_short_description( (string) $input['short_description'] );
					}
					if ( ! empty( $input['manage_stock'] ) ) {
						$product->set_manage_stock( true );
						if ( isset( $input['stock_qty'] ) ) {
							$product->set_stock_quantity( (int) $input['stock_qty'] );
						}
					}
					if ( ! empty( $input['categories'] ) ) {
						$product->set_category_ids( array_map( 'intval', (array) $input['categories'] ) );
					}
					if ( ! empty( $input['tags'] ) ) {
						$product->set_tag_ids( array_map( 'intval', (array) $input['tags'] ) );
					}
					if ( ! empty( $input['images'] ) ) {
						$image_ids = array_map( 'intval', (array) $input['images'] );
						$product->set_image_id( (int) array_shift( $image_ids ) );
						if ( ! empty( $image_ids ) ) {
							$product->set_gallery_image_ids( $image_ids );
						}
					}
					if ( isset( $input['featured'] ) ) {
						$product->set_featured( (bool) $input['featured'] );
					}
					$id = $product->save();
					return [ 'created' => true, 'id' => (int) $id, 'permalink' => get_permalink( (int) $id ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'publish_products' ) || tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => false ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/update-product',
			[
				'label'               => 'WooCommerce: update product',
     'category'            => 'tropk-core',
				'description'         => 'Update fields on an existing product. Pass only the fields you want to change.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'id' ],
					'properties' => [
						'id'                 => [ 'type' => 'integer', 'minimum' => 1 ],
						'name'               => [ 'type' => 'string' ],
						'sku'                => [ 'type' => 'string' ],
						'regular_price'      => [ 'type' => 'string' ],
						'sale_price'         => [ 'type' => 'string' ],
						'description'        => [ 'type' => 'string' ],
						'short_description'  => [ 'type' => 'string' ],
						'status'             => [ 'type' => 'string', 'enum' => [ 'draft', 'publish', 'pending', 'private' ] ],
						'stock_qty'          => [ 'type' => 'integer' ],
						'manage_stock'       => [ 'type' => 'boolean' ],
						'stock_status'       => [ 'type' => 'string', 'enum' => [ 'instock', 'outofstock', 'onbackorder' ] ],
						'featured'           => [ 'type' => 'boolean' ],
						'categories'         => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$product = wc_get_product( (int) $input['id'] );
					if ( ! $product ) {
						throw new \RuntimeException( sprintf( 'Product %d not found.', (int) $input['id'] ) );
					}
					$setters = [
						'name'              => 'set_name',
						'sku'               => 'set_sku',
						'regular_price'     => 'set_regular_price',
						'sale_price'        => 'set_sale_price',
						'description'       => 'set_description',
						'short_description' => 'set_short_description',
						'status'            => 'set_status',
						'manage_stock'      => 'set_manage_stock',
						'stock_status'      => 'set_stock_status',
						'featured'          => 'set_featured',
					];
					foreach ( $setters as $key => $method ) {
						if ( array_key_exists( $key, $input ) ) {
							$product->$method( $input[ $key ] );
						}
					}
					if ( isset( $input['stock_qty'] ) ) {
						$product->set_stock_quantity( (int) $input['stock_qty'] );
					}
					if ( isset( $input['categories'] ) ) {
						$product->set_category_ids( array_map( 'intval', (array) $input['categories'] ) );
					}
					$product->save();
					return [ 'updated' => true, 'id' => $product->get_id() ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_products' ) || tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/delete-product',
			[
				'label'               => 'WooCommerce: delete product',
     'category'            => 'tropk-core',
				'description'         => 'Delete (trash or force-delete) a product.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'id' ],
					'properties' => [
						'id'    => [ 'type' => 'integer', 'minimum' => 1 ],
						'force' => [ 'type' => 'boolean', 'default' => false ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$product = wc_get_product( (int) $input['id'] );
					if ( ! $product ) {
						throw new \RuntimeException( sprintf( 'Product %d not found.', (int) $input['id'] ) );
					}
					$ok = $product->delete( (bool) ( $input['force'] ?? false ) );
					return [ 'deleted' => (bool) $ok, 'id' => (int) $input['id'] ];
				},
				'permission_callback' => static fn() => current_user_can( 'delete_products' ) || tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'destructive' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/update-stock',
			[
				'label'               => 'WooCommerce: update stock',
     'category'            => 'tropk-core',
				'description'         => 'Set the stock quantity (and optionally manage_stock + stock_status) for a product.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'id', 'qty' ],
					'properties' => [
						'id'           => [ 'type' => 'integer', 'minimum' => 1 ],
						'qty'          => [ 'type' => 'integer' ],
						'stock_status' => [ 'type' => 'string', 'enum' => [ 'instock', 'outofstock', 'onbackorder' ] ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$product = wc_get_product( (int) $input['id'] );
					if ( ! $product ) {
						throw new \RuntimeException( sprintf( 'Product %d not found.', (int) $input['id'] ) );
					}
					$product->set_manage_stock( true );
					$product->set_stock_quantity( (int) $input['qty'] );
					if ( ! empty( $input['stock_status'] ) ) {
						$product->set_stock_status( (string) $input['stock_status'] );
					}
					$product->save();
					return [ 'updated' => true, 'id' => $product->get_id(), 'qty' => $product->get_stock_quantity(), 'status' => $product->get_stock_status() ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_products' ) || tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/get-orders',
			[
				'label'               => 'WooCommerce: list orders',
     'category'            => 'tropk-core',
				'description'         => 'List orders with filtering by status, customer, date range.',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'per_page'    => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
						'page'        => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
						'status'      => [ 'type' => 'string' ],
						'customer_id' => [ 'type' => 'integer' ],
						'after'       => [ 'type' => 'string', 'description' => 'YYYY-MM-DD' ],
						'before'      => [ 'type' => 'string', 'description' => 'YYYY-MM-DD' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$args = [
						'limit'   => (int) ( $input['per_page'] ?? 20 ),
						'page'    => (int) ( $input['page'] ?? 1 ),
						'orderby' => 'date',
						'order'   => 'DESC',
					];
					if ( ! empty( $input['status'] ) ) {
						$args['status'] = (string) $input['status'];
					}
					if ( ! empty( $input['customer_id'] ) ) {
						$args['customer_id'] = (int) $input['customer_id'];
					}
					if ( ! empty( $input['after'] ) ) {
						$args['date_created'] = '>=' . (string) $input['after'];
					}
					if ( ! empty( $input['before'] ) ) {
						$args['date_created'] = isset( $args['date_created'] ) ? $args['date_created'] . '...' . (string) $input['before'] : '<=' . (string) $input['before'];
					}
					$orders = wc_get_orders( $args );
					$out    = [];
					foreach ( $orders as $o ) {
						$out[] = [
							'id'         => $o->get_id(),
							'number'     => $o->get_order_number(),
							'status'     => $o->get_status(),
							'total'      => $o->get_total(),
							'currency'   => $o->get_currency(),
							'customer_id'=> $o->get_customer_id(),
							'created'    => $o->get_date_created() ? $o->get_date_created()->date( 'c' ) : null,
						];
					}
					return [ 'orders' => $out, 'count' => count( $out ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_shop_orders' ) || tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/get-order',
			[
				'label'               => 'WooCommerce: get order',
     'category'            => 'tropk-core',
				'description'         => 'Get a single order with line items.',
				'input_schema'        => [ 'type' => 'object', 'required' => [ 'id' ], 'properties' => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$order = wc_get_order( (int) $input['id'] );
					if ( ! $order ) {
						throw new \RuntimeException( sprintf( 'Order %d not found.', (int) $input['id'] ) );
					}
					$items = [];
					foreach ( $order->get_items() as $item ) {
						$items[] = [
							'product_id' => $item->get_product_id(),
							'name'       => $item->get_name(),
							'qty'        => $item->get_quantity(),
							'subtotal'   => $item->get_subtotal(),
							'total'      => $item->get_total(),
						];
					}
					return [
						'id'            => $order->get_id(),
						'number'        => $order->get_order_number(),
						'status'        => $order->get_status(),
						'total'         => $order->get_total(),
						'currency'      => $order->get_currency(),
						'customer_id'   => $order->get_customer_id(),
						'billing_email' => $order->get_billing_email(),
						'items'         => $items,
					];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_shop_orders' ) || tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/update-order-status',
			[
				'label'               => 'WooCommerce: update order status',
     'category'            => 'tropk-core',
				'description'         => 'Transition an order to a new status (pending, processing, on-hold, completed, cancelled, refunded, failed).',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'id', 'status' ],
					'properties' => [
						'id'     => [ 'type' => 'integer', 'minimum' => 1 ],
						'status' => [ 'type' => 'string' ],
						'note'   => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$order = wc_get_order( (int) $input['id'] );
					if ( ! $order ) {
						throw new \RuntimeException( sprintf( 'Order %d not found.', (int) $input['id'] ) );
					}
					$order->update_status( (string) $input['status'], (string) ( $input['note'] ?? '' ) );
					return [ 'updated' => true, 'id' => $order->get_id(), 'status' => $order->get_status() ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_shop_orders' ) || tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/get-customers',
			[
				'label'               => 'WooCommerce: list customers',
     'category'            => 'tropk-core',
				'description'         => 'List customers (users with role customer).',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
						'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
						'search'   => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$args = [
						'role'   => 'customer',
						'number' => (int) ( $input['per_page'] ?? 20 ),
						'paged'  => (int) ( $input['page'] ?? 1 ),
					];
					if ( ! empty( $input['search'] ) ) {
						$args['search'] = '*' . esc_sql( (string) $input['search'] ) . '*';
					}
					$query = new \WP_User_Query( $args );
					$out   = [];
					foreach ( $query->get_results() as $u ) {
						$out[] = [
							'id'           => $u->ID,
							'email'        => $u->user_email,
							'display_name' => $u->display_name,
							'registered'   => $u->user_registered,
						];
					}
					return [ 'customers' => $out, 'total' => (int) $query->get_total() ];
				},
				'permission_callback' => static fn() => current_user_can( 'list_users' ) || tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/create-coupon',
			[
				'label'               => 'WooCommerce: create coupon',
     'category'            => 'tropk-core',
				'description'         => 'Create a new coupon.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'code' ],
					'properties' => [
						'code'           => [ 'type' => 'string', 'minLength' => 2 ],
						'discount_type'  => [ 'type' => 'string', 'enum' => [ 'percent', 'fixed_cart', 'fixed_product' ], 'default' => 'percent' ],
						'amount'         => [ 'type' => 'string', 'default' => '0' ],
						'description'    => [ 'type' => 'string' ],
						'expires'        => [ 'type' => 'string', 'description' => 'YYYY-MM-DD' ],
						'individual_use' => [ 'type' => 'boolean' ],
						'usage_limit'    => [ 'type' => 'integer', 'minimum' => 0 ],
						'minimum_amount' => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$coupon = new \WC_Coupon();
					$coupon->set_code( (string) $input['code'] );
					$coupon->set_discount_type( (string) ( $input['discount_type'] ?? 'percent' ) );
					$coupon->set_amount( (string) ( $input['amount'] ?? '0' ) );
					if ( ! empty( $input['description'] ) ) {
						$coupon->set_description( (string) $input['description'] );
					}
					if ( ! empty( $input['expires'] ) ) {
						$coupon->set_date_expires( (string) $input['expires'] );
					}
					if ( isset( $input['individual_use'] ) ) {
						$coupon->set_individual_use( (bool) $input['individual_use'] );
					}
					if ( isset( $input['usage_limit'] ) ) {
						$coupon->set_usage_limit( (int) $input['usage_limit'] );
					}
					if ( isset( $input['minimum_amount'] ) ) {
						$coupon->set_minimum_amount( (string) $input['minimum_amount'] );
					}
					$id = $coupon->save();
					return [ 'created' => true, 'id' => (int) $id, 'code' => $coupon->get_code() ];
				},
				'permission_callback' => static fn() => tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'destructive' => false, 'idempotent' => false ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/get-coupons',
			[
				'label'               => 'WooCommerce: list coupons',
     'category'            => 'tropk-core',
				'description'         => 'List existing coupons.',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
						'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$query = new \WP_Query(
						[
							'post_type'      => 'shop_coupon',
							'posts_per_page' => (int) ( $input['per_page'] ?? 20 ),
							'paged'          => (int) ( $input['page'] ?? 1 ),
						]
					);
					$out = [];
					foreach ( $query->posts as $p ) {
						$c     = new \WC_Coupon( $p->ID );
						$out[] = [
							'id'            => $c->get_id(),
							'code'          => $c->get_code(),
							'discount_type' => $c->get_discount_type(),
							'amount'        => $c->get_amount(),
							'usage_count'   => $c->get_usage_count(),
							'usage_limit'   => $c->get_usage_limit(),
							'expires'       => $c->get_date_expires() ? $c->get_date_expires()->date( 'Y-m-d' ) : null,
						];
					}
					return [ 'coupons' => $out, 'total' => (int) $query->found_posts ];
				},
				'permission_callback' => static fn() => tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/get-payment-gateways',
			[
				'label'               => 'WooCommerce: list payment gateways',
     'category'            => 'tropk-core',
				'description'         => 'Lists all registered payment gateways and their enabled state.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					tropk_mcp_wc_require_active();
					$out = [];
					foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gw ) {
						$out[] = [
							'id'          => $id,
							'title'       => $gw->title,
							'description' => $gw->description,
							'enabled'     => 'yes' === $gw->enabled,
							'method_title'=> $gw->method_title,
						];
					}
					return [ 'gateways' => $out ];
				},
				'permission_callback' => static fn() => tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/get-shipping-zones',
			[
				'label'               => 'WooCommerce: list shipping zones',
     'category'            => 'tropk-core',
				'description'         => 'Lists shipping zones with their methods and locations.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					tropk_mcp_wc_require_active();
					$zones = \WC_Shipping_Zones::get_zones();
					return [ 'zones' => array_values( $zones ) ];
				},
				'permission_callback' => static fn() => tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/get-product-categories',
			[
				'label'               => 'WooCommerce: list product categories',
     'category'            => 'tropk-core',
				'description'         => 'Lists product_cat terms with counts.',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'hide_empty' => [ 'type' => 'boolean', 'default' => false ],
						'parent'     => [ 'type' => 'integer' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$args = [
						'taxonomy'   => 'product_cat',
						'hide_empty' => (bool) ( $input['hide_empty'] ?? false ),
					];
					if ( isset( $input['parent'] ) ) {
						$args['parent'] = (int) $input['parent'];
					}
					$terms = get_terms( $args );
					if ( is_wp_error( $terms ) ) {
						throw new \RuntimeException( $terms->get_error_message() );
					}
					$out = [];
					foreach ( $terms as $t ) {
						$out[] = [
							'id'     => $t->term_id,
							'name'   => $t->name,
							'slug'   => $t->slug,
							'parent' => $t->parent,
							'count'  => $t->count,
						];
					}
					return [ 'categories' => $out ];
				},
				'permission_callback' => static fn() => current_user_can( 'edit_products' ) || tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/get-sales-report',
			[
				'label'               => 'WooCommerce: sales report',
     'category'            => 'tropk-core',
				'description'         => 'Returns totals (gross sales, net sales, orders, items, refunded amount) for a date range.',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'after'  => [ 'type' => 'string', 'description' => 'YYYY-MM-DD (inclusive).' ],
						'before' => [ 'type' => 'string', 'description' => 'YYYY-MM-DD (inclusive).' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$args = [ 'limit' => -1, 'return' => 'objects' ];
					if ( ! empty( $input['after'] ) || ! empty( $input['before'] ) ) {
						$after  = (string) ( $input['after'] ?? '1970-01-01' );
						$before = (string) ( $input['before'] ?? gmdate( 'Y-m-d' ) );
						$args['date_created'] = $after . '...' . $before;
					}
					$args['status'] = [ 'wc-completed', 'wc-processing' ];
					$orders         = wc_get_orders( $args );
					$gross          = 0.0;
					$items          = 0;
					$refunded       = 0.0;
					foreach ( $orders as $o ) {
						$gross    += (float) $o->get_total();
						$refunded += (float) $o->get_total_refunded();
						foreach ( $o->get_items() as $line ) {
							$items += (int) $line->get_quantity();
						}
					}
					return [
						'orders'      => count( $orders ),
						'gross_sales' => round( $gross, 2 ),
						'net_sales'   => round( $gross - $refunded, 2 ),
						'refunded'    => round( $refunded, 2 ),
						'items_sold'  => $items,
					];
				},
				'permission_callback' => static fn() => current_user_can( 'view_woocommerce_reports' ) || tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-wc/get-top-sellers',
			[
				'label'               => 'WooCommerce: top sellers',
     'category'            => 'tropk-core',
				'description'         => 'Returns the top N products by total_sales (lifetime).',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10 ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					tropk_mcp_wc_require_active();
					$query = new \WP_Query(
						[
							'post_type'      => 'product',
							'posts_per_page' => (int) ( $input['limit'] ?? 10 ),
							'meta_key'       => 'total_sales',
							'orderby'        => 'meta_value_num',
							'order'          => 'DESC',
						]
					);
					$out = [];
					foreach ( $query->posts as $p ) {
						$prod  = wc_get_product( $p->ID );
						$out[] = [
							'id'          => $prod->get_id(),
							'name'        => $prod->get_name(),
							'sku'         => $prod->get_sku(),
							'total_sales' => (int) get_post_meta( $p->ID, 'total_sales', true ),
							'price'       => $prod->get_price(),
						];
					}
					return [ 'products' => $out ];
				},
				'permission_callback' => static fn() => current_user_can( 'view_woocommerce_reports' ) || tropk_mcp_wc_can_edit_shop(),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);
	},
	20
);
