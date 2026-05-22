<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddIconBoxAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'icon-box';
	}

	protected function widget_label(): string {
		return 'icon box';
	}

	protected function default_settings(): array {
		return [
			'selected_icon' => [
				'value' => 'fas fa-star',
				'library' => 'fa-solid',
			],
			'title_text' => 'Title',
			'description_text' => 'Description text goes here.',
		];
	}
}
