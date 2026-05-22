<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddCounterAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'counter';
	}

	protected function widget_label(): string {
		return 'counter';
	}

	protected function default_settings(): array {
		return [
			'starting_number' => 0,
			'ending_number' => 100,
			'title' => 'Counter',
		];
	}
}
