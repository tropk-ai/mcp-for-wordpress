<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddRatingAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'rating';
	}

	protected function widget_label(): string {
		return 'rating';
	}

	protected function default_settings(): array {
		return [
			'rating_scale' => 5,
			'rating' => 5,
		];
	}
}
