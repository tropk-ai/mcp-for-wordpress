<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddPostsAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'posts';
	}

	protected function widget_label(): string {
		return 'posts (Pro)';
	}

	protected function default_settings(): array {
		return [
			'posts_per_page' => 6,
		];
	}
}
