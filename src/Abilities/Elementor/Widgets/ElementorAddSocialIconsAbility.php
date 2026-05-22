<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddSocialIconsAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'social-icons';
	}

	protected function widget_label(): string {
		return 'social icons';
	}

	protected function default_settings(): array {
		return [
			'social_icon_list' => [
				[
					'social_icon' => [
						'value' => 'fab fa-facebook',
						'library' => 'fa-brands',
					],
					'link' => [
						'url' => 'https://facebook.com',
					],
				],
			],
		];
	}
}
