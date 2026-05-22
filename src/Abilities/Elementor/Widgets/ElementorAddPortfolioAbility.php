<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddPortfolioAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'portfolio';
	}

	protected function widget_label(): string {
		return 'portfolio (Pro)';
	}

	protected function default_settings(): array {
		return [
			'posts_per_page' => 6,
		];
	}
}
