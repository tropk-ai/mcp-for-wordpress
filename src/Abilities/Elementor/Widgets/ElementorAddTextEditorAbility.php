<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddTextEditorAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'text-editor';
	}

	protected function widget_label(): string {
		return 'text editor';
	}

	protected function default_settings(): array {
		return [
			'editor' => '<p>Edit this text.</p>',
			'align' => 'left',
		];
	}
}
