<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddAHeadingAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'a-heading';
	}

	protected function widget_label(): string {
		return 'atomic heading (legacy a-)';
	}

	protected function default_settings(): array {
		return [
			'title' => [
				'$$type' => 'string',
				'value' => 'New heading',
			],
			'tag' => [
				'$$type' => 'string',
				'value' => 'h2',
			],
		];
	}
}
