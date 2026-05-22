<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddEDividerAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'e-divider';
	}

	protected function widget_label(): string {
		return 'atomic divider (V4)';
	}

	protected function default_settings(): array {
		return [];
	}
}
