<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddWoocommerceProductMetaAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'woocommerce-product-meta';
	}

	protected function widget_label(): string {
		return 'WooCommerce product meta (Pro)';
	}

	protected function default_settings(): array {
		return [];
	}
}
