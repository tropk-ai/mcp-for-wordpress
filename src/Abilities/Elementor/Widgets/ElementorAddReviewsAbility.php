<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddReviewsAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'reviews';
	}

	protected function widget_label(): string {
		return 'reviews (Pro)';
	}

	protected function default_settings(): array {
		return [
			'slides_to_show' => 3,
		];
	}
}
