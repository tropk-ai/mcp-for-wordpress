<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddToggleAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'toggle';
	}

	protected function widget_label(): string {
		return 'toggle';
	}

	protected function default_settings(): array {
		return [
			'tabs' => [
				[
					'tab_title' => 'Toggle #1',
					'tab_content' => 'Content #1',
				],
			],
		];
	}
}
