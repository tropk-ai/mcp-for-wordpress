<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddPaypalButtonAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'paypal-button';
	}

	protected function widget_label(): string {
		return 'PayPal button (Pro)';
	}

	protected function default_settings(): array {
		return [
			'merchant_email' => '',
			'item_name' => 'Item',
			'price' => '10.00',
			'currency' => 'USD',
		];
	}
}
