<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddTextPathAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'text-path';
	}

	protected function widget_label(): string {
		return 'text path';
	}

	protected function default_settings(): array {
		return [
			'text' => 'Text along a path',
		];
	}
}
