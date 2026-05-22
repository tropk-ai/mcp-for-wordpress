<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddFlipBoxAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'flip-box';
	}

	protected function widget_label(): string {
		return 'flip box (Pro)';
	}

	protected function default_settings(): array {
		return [
			'title_text_a' => 'This is the heading',
			'description_text_a' => 'Front side text.',
			'title_text_b' => 'This is the heading',
			'description_text_b' => 'Back side text.',
		];
	}
}
