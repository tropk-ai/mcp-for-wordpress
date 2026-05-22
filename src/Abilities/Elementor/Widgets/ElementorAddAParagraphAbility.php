<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddAParagraphAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'a-paragraph';
	}

	protected function widget_label(): string {
		return 'atomic paragraph (legacy a-)';
	}

	protected function default_settings(): array {
		return [
			'paragraph' => [
				'$$type' => 'string',
				'value' => 'Add your paragraph text here.',
			],
		];
	}
}
