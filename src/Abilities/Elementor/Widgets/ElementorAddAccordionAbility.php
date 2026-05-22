<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddAccordionAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'accordion';
	}

	protected function widget_label(): string {
		return 'accordion';
	}

	protected function default_settings(): array {
		return [
			'tabs' => [
				[
					'tab_title' => 'Accordion #1',
					'tab_content' => 'Content #1',
				],
				[
					'tab_title' => 'Accordion #2',
					'tab_content' => 'Content #2',
				],
			],
		];
	}
}
