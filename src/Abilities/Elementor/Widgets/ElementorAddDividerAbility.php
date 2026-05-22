<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddDividerAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'divider';
	}

	protected function widget_label(): string {
		return 'divider';
	}

	protected function default_settings(): array {
		return [
			'style' => 'solid',
			'weight' => [
				'unit' => 'px',
				'size' => 1,
			],
			'width' => [
				'unit' => '%',
				'size' => 100,
			],
		];
	}
}
