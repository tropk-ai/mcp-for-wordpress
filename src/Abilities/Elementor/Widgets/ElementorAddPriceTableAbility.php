<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddPriceTableAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'price-table';
	}

	protected function widget_label(): string {
		return 'price table (Pro)';
	}

	protected function default_settings(): array {
		return [
			'heading' => 'Pricing Plan',
			'price' => 9.99,
			'currency_symbol' => '$',
		];
	}
}
