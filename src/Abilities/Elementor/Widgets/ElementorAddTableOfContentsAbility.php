<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddTableOfContentsAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'table-of-contents';
	}

	protected function widget_label(): string {
		return 'table of contents (Pro)';
	}

	protected function default_settings(): array {
		return [
			'title' => 'Table of Contents',
			'headings_by_tags' => [
				'h2',
				'h3',
			],
		];
	}
}
