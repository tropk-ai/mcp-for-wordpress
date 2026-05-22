<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddMenuAnchorAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'menu-anchor';
	}

	protected function widget_label(): string {
		return 'menu anchor';
	}

	protected function default_settings(): array {
		return [
			'anchor' => 'anchor-id',
		];
	}
}
