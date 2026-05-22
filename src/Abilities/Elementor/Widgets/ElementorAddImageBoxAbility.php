<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddImageBoxAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'image-box';
	}

	protected function widget_label(): string {
		return 'image box';
	}

	protected function default_settings(): array {
		return [
			'title_text' => 'Title',
			'description_text' => 'Description text goes here.',
		];
	}
}
