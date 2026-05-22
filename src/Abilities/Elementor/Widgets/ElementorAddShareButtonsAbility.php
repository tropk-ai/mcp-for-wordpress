<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddShareButtonsAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'share-buttons';
	}

	protected function widget_label(): string {
		return 'share buttons (Pro)';
	}

	protected function default_settings(): array {
		return [
			'share_buttons' => [
				[
					'button' => 'facebook',
				],
				[
					'button' => 'twitter',
				],
			],
		];
	}
}
