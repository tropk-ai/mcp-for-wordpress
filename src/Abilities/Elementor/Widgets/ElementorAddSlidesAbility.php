<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddSlidesAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'slides';
	}

	protected function widget_label(): string {
		return 'slides (Pro)';
	}

	protected function default_settings(): array {
		return [
			'slides' => [
				[
					'heading' => 'Slide #1',
					'description' => 'Description.',
				],
			],
		];
	}
}
