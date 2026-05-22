<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorSetTemplateConditionsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-set-template-conditions'; }
	protected function meta(): array { return [ 'label' => __( 'Set Theme Builder display conditions', 'mcp-for-wordpress' ), 'description' => __( 'Overwrites the _elementor_conditions meta for an elementor_library item with the supplied array.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'template_id', 'conditions' ], 'properties' => [ 'template_id' => [ 'type' => 'integer' ], 'conditions' => [ 'type' => 'array' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['template_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		update_post_meta( (int) $input['template_id'], '_elementor_conditions', (array) $input['conditions'] );
		return [ 'updated' => true ];
	}
}
