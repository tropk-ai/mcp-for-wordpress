<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddBasicGalleryAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'basic-gallery';
	}

	protected function widget_label(): string {
		return 'basic gallery';
	}

	protected function default_settings(): array {
		return [
			'gallery_columns' => 4,
		];
	}
}
