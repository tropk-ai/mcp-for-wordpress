<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddImageCarouselAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'image-carousel';
	}

	protected function widget_label(): string {
		return 'image carousel';
	}

	protected function default_settings(): array {
		return [
			'slides_to_show' => '3',
			'autoplay' => 'yes',
		];
	}
}
