<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddWoocommerceProductTitleAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'woocommerce-product-title';
	}

	protected function widget_label(): string {
		return 'WooCommerce product title (Pro)';
	}

	protected function default_settings(): array {
		return [];
	}
}
