<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddSearchFormAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'search-form';
	}

	protected function widget_label(): string {
		return 'search form (Pro)';
	}

	protected function default_settings(): array {
		return [
			'placeholder' => 'Search...',
		];
	}
}
