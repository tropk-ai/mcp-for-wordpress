<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddASvgAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'a-svg';
	}

	protected function widget_label(): string {
		return 'atomic SVG (legacy a-)';
	}

	protected function default_settings(): array {
		return [
			'svg' => [
				'$$type' => 'string',
				'value' => '',
			],
		];
	}
}
