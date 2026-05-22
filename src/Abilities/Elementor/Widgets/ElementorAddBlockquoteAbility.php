<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddBlockquoteAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'blockquote';
	}

	protected function widget_label(): string {
		return 'blockquote (Pro)';
	}

	protected function default_settings(): array {
		return [
			'blockquote_content' => 'Add your quote here.',
			'author_name' => 'Author',
		];
	}
}
