<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddSoundCloudAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'sound-cloud';
	}

	protected function widget_label(): string {
		return 'SoundCloud';
	}

	protected function default_settings(): array {
		return [
			'link' => [
				'url' => 'https://soundcloud.com/',
			],
		];
	}
}
