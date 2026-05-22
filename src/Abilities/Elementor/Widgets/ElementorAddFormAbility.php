<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor\Widgets;

final class ElementorAddFormAbility extends AbstractElementorAddWidgetAbility {

	protected function widget_type(): string {
		return 'form';
	}

	protected function widget_label(): string {
		return 'form (Pro)';
	}

	protected function default_settings(): array {
		return [
			'form_name' => 'New Form',
			'form_fields' => [
				[
					'custom_id' => 'name',
					'field_type' => 'text',
					'field_label' => 'Name',
					'required' => 'true',
				],
				[
					'custom_id' => 'email',
					'field_type' => 'email',
					'field_label' => 'Email',
					'required' => 'true',
				],
				[
					'custom_id' => 'message',
					'field_type' => 'textarea',
					'field_label' => 'Message',
				],
			],
		];
	}
}
