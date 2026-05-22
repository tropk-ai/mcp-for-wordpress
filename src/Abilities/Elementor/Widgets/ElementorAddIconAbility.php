<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddIconAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'icon';
	}

	protected function widget_label(): string {
		return 'icon';
	}

	protected function default_settings(): array {
		return [
			'selected_icon' => [
				'value' => 'fas fa-star',
				'library' => 'fa-solid',
			],
			'align' => 'center',
		];
	}
}
