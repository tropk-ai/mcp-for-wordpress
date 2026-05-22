<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddTestimonialCarouselAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'testimonial-carousel';
	}

	protected function widget_label(): string {
		return 'testimonial carousel (Pro)';
	}

	protected function default_settings(): array {
		return [
			'slides_to_show' => 3,
		];
	}
}
