<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddEButtonAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'e-button';
	}

	protected function widget_label(): string {
		return 'atomic button (V4)';
	}

	protected function default_settings(): array {
		return [
			'text' => [
				'$$type' => 'string',
				'value' => 'Click here',
			],
			'link' => [
				'$$type' => 'link',
				'value' => [
					'destination' => '#',
				],
			],
		];
	}
}
