<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddCallToActionAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'call-to-action';
	}

	protected function widget_label(): string {
		return 'call to action (Pro)';
	}

	protected function default_settings(): array {
		return [
			'title' => 'Title',
			'description' => 'Click on the edit button to change this text.',
			'button' => 'Click Here',
		];
	}
}
