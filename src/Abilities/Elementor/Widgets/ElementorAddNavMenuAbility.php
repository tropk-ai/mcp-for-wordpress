<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddNavMenuAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'nav-menu';
	}

	protected function widget_label(): string {
		return 'nav menu (Pro)';
	}

	protected function default_settings(): array {
		return [
			'layout' => 'horizontal',
		];
	}
}
