<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddVideoPlaylistAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'video-playlist';
	}

	protected function widget_label(): string {
		return 'video playlist (Pro)';
	}

	protected function default_settings(): array {
		return [
			'tabs' => [],
		];
	}
}
