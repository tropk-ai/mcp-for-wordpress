<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddAnimatedHeadlineAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'animated-headline';
	}

	protected function widget_label(): string {
		return 'animated headline (Pro)';
	}

	protected function default_settings(): array {
		return [
			'before_text' => 'This is',
			'highlighted_text' => 'awesome',
			'after_text' => 'text.',
		];
	}
}
