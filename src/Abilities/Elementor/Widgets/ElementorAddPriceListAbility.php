<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddPriceListAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'price-list';
	}

	protected function widget_label(): string {
		return 'price list (Pro)';
	}

	protected function default_settings(): array {
		return [
			'price_list' => [
				[
					'title' => 'Item',
					'item_description' => 'Description',
					'price' => '$10',
				],
			],
		];
	}
}
