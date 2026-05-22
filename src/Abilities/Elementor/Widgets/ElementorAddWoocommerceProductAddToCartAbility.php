<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddWoocommerceProductAddToCartAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'woocommerce-product-add-to-cart';
	}

	protected function widget_label(): string {
		return 'WooCommerce product add to cart (Pro)';
	}

	protected function default_settings(): array {
		return [];
	}
}
