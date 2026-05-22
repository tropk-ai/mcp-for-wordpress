<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddEImageAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'e-image';
	}

	protected function widget_label(): string {
		return 'atomic image (V4)';
	}

	protected function default_settings(): array {
		return [
			'image' => [
				'$$type' => 'image-attachment',
				'value' => [
					'id' => 0,
					'url' => '',
				],
			],
		];
	}
}
