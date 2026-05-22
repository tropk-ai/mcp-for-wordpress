<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddAudioAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'audio';
	}

	protected function widget_label(): string {
		return 'audio';
	}

	protected function default_settings(): array {
		return [
			'link' => [
				'url' => 'https://soundcloud.com/',
			],
		];
	}
}
