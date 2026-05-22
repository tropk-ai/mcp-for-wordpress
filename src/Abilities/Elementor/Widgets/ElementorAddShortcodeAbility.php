<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddShortcodeAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'shortcode';
	}

	protected function widget_label(): string {
		return 'shortcode';
	}

	protected function default_settings(): array {
		return [
			'shortcode' => '',
		];
	}
}
