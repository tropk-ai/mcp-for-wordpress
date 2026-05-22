<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddHtmlAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'html';
	}

	protected function widget_label(): string {
		return 'HTML';
	}

	protected function default_settings(): array {
		return [
			'html' => '<!-- HTML code here -->',
		];
	}
}
