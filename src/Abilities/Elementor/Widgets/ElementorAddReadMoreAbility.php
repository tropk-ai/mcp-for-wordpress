<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddReadMoreAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'read-more';
	}

	protected function widget_label(): string {
		return 'read more';
	}

	protected function default_settings(): array {
		return [];
	}
}
