<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddESvgAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'e-svg';
	}

	protected function widget_label(): string {
		return 'atomic SVG (V4)';
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
