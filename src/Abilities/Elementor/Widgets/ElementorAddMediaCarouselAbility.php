<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddMediaCarouselAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'media-carousel';
	}

	protected function widget_label(): string {
		return 'media carousel (Pro)';
	}

	protected function default_settings(): array {
		return [
			'slides_to_show' => 3,
			'autoplay' => 'yes',
		];
	}
}
