<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddAlertAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'alert';
	}

	protected function widget_label(): string {
		return 'alert';
	}

	protected function default_settings(): array {
		return [
			'alert_title' => 'This is an Alert',
			'alert_description' => 'Description text.',
			'alert_type' => 'info',
		];
	}
}
