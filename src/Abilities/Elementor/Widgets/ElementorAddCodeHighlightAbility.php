<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddCodeHighlightAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'code-highlight';
	}

	protected function widget_label(): string {
		return 'code highlight (Pro)';
	}

	protected function default_settings(): array {
		return [
			'language' => 'javascript',
			'code' => 'console.log("hello");',
		];
	}
}
