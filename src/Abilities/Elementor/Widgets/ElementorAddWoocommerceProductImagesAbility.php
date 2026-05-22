<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddWoocommerceProductImagesAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'woocommerce-product-images';
	}

	protected function widget_label(): string {
		return 'WooCommerce product images (Pro)';
	}

	protected function default_settings(): array {
		return [];
	}
}
