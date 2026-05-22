<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddLoginAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'login';
	}

	protected function widget_label(): string {
		return 'login (Pro)';
	}

	protected function default_settings(): array {
		return [];
	}
}
