<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddGalleryAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'gallery';
	}

	protected function widget_label(): string {
		return 'gallery (Pro)';
	}

	protected function default_settings(): array {
		return [];
	}
}
