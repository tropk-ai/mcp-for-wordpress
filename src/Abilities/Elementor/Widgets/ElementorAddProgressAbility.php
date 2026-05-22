<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddProgressAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'progress';
	}

	protected function widget_label(): string {
		return 'progress bar';
	}

	protected function default_settings(): array {
		return [
			'title' => 'My Skill',
			'percent' => [
				'unit' => '%',
				'size' => 50,
			],
		];
	}
}
