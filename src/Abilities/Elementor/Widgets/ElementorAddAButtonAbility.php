<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddAButtonAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'a-button';
	}

	protected function widget_label(): string {
		return 'atomic button (legacy a-)';
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
