<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddButtonAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'button';
	}

	protected function widget_label(): string {
		return 'button';
	}

	protected function default_settings(): array {
		return [
			'text' => 'Click here',
			'link' => [
				'url' => '#',
				'is_external' => '',
				'nofollow' => '',
			],
			'align' => 'left',
			'size' => 'sm',
		];
	}
}
