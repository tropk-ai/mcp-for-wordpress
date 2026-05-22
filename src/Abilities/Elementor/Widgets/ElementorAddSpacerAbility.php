<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddSpacerAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'spacer';
	}

	protected function widget_label(): string {
		return 'spacer';
	}

	protected function default_settings(): array {
		return [
			'space' => [
				'unit' => 'px',
				'size' => 50,
			],
		];
	}
}
