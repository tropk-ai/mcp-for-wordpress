<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddTestimonialAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'testimonial';
	}

	protected function widget_label(): string {
		return 'testimonial';
	}

	protected function default_settings(): array {
		return [
			'testimonial_content' => 'Great product!',
			'testimonial_name' => 'Customer Name',
			'testimonial_job' => 'Job Title',
		];
	}
}
