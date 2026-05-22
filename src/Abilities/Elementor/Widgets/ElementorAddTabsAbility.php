<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddTabsAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'tabs';
	}

	protected function widget_label(): string {
		return 'tabs';
	}

	protected function default_settings(): array {
		return [
			'tabs' => [
				[
					'tab_title' => 'Tab #1',
					'tab_content' => 'Content #1',
				],
				[
					'tab_title' => 'Tab #2',
					'tab_content' => 'Content #2',
				],
			],
		];
	}
}
