<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddADividerAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'a-divider';
	}

	protected function widget_label(): string {
		return 'atomic divider (legacy a-)';
	}

	protected function default_settings(): array {
		return [];
	}
}
