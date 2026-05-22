<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddStarRatingAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'star-rating';
	}

	protected function widget_label(): string {
		return 'star rating';
	}

	protected function default_settings(): array {
		return [
			'rating' => 5,
			'rating_scale' => 5,
		];
	}
}
