<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddImageGalleryAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'image-gallery';
	}

	protected function widget_label(): string {
		return 'image gallery';
	}

	protected function default_settings(): array {
		return [
			'gallery_columns' => 4,
			'gallery_link' => 'file',
		];
	}
}
