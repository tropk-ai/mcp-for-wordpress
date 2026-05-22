<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddIconListAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'icon-list';
	}

	protected function widget_label(): string {
		return 'icon list';
	}

	protected function default_settings(): array {
		return [
			'icon_list' => [
				[
					'text' => 'List Item #1',
					'selected_icon' => [
						'value' => 'fas fa-check',
						'library' => 'fa-solid',
					],
				],
			],
		];
	}
}
