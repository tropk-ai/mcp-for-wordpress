<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddLottieAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'lottie';
	}

	protected function widget_label(): string {
		return 'lottie (Pro)';
	}

	protected function default_settings(): array {
		return [
			'source_json' => [
				'url' => '',
			],
		];
	}
}
