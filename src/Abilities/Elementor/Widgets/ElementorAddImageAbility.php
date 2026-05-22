<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddImageAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'image';
	}

	protected function widget_label(): string {
		return 'image';
	}

	protected function default_settings(): array {
		return [
			'image_size' => 'large',
			'align' => 'center',
			'image' => [
				'url' => '',
				'id' => 0,
			],
		];
	}
}
