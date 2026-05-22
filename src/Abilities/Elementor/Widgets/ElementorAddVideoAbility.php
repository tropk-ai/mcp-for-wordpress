<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddVideoAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'video';
	}

	protected function widget_label(): string {
		return 'video';
	}

	protected function default_settings(): array {
		return [
			'video_type' => 'youtube',
			'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
		];
	}
}
