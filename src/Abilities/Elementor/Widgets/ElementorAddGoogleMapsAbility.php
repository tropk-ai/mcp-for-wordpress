<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddGoogleMapsAbility extends AbstractElementorAddWidgetAbility {

	// Elementor's widget type slug uses an underscore but the Abilities API
	// only accepts dashes/lowercase in slugs, so override slug() explicitly.
	public function slug(): string {
		return 'elementor-add-google-maps';
	}

	protected function widget_type(): string {
		return 'google_maps';
	}

	protected function widget_label(): string {
		return 'Google Maps';
	}

	protected function default_settings(): array {
		return [
			'address' => 'London, UK',
			'zoom' => [
				'unit' => 'px',
				'size' => 10,
			],
			'height' => [
				'unit' => 'px',
				'size' => 300,
			],
		];
	}
}
