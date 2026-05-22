<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddHeadingAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'heading';
	}

	protected function widget_label(): string {
		return 'heading';
	}

	protected function default_settings(): array {
		return [
			'title' => 'New heading',
			'header_size' => 'h2',
			'align' => 'left',
		];
	}
}
