<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddWoocommerceProductsAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'woocommerce-products';
	}

	protected function widget_label(): string {
		return 'WooCommerce products (Pro)';
	}

	protected function default_settings(): array {
		return [
			'columns' => 4,
			'rows' => 4,
		];
	}
}
