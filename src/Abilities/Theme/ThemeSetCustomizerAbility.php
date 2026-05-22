<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Theme;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ThemeSetCustomizerAbility extends AbstractAbility {
	public function slug(): string { return 'theme-set-customizer'; }
	protected function meta(): array { return [ 'label' => __( 'Set a Customizer theme mod', 'mcp-for-wordpress' ), 'description' => __( 'Updates a single theme mod (set_theme_mod) — colors, header text, custom logo etc.', 'mcp-for-wordpress' ), 'destructive' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'key', 'value' ], 'properties' => [ 'key' => [ 'type' => 'string' ], 'value' => [] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_theme_options' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array { set_theme_mod( (string) $input['key'], $input['value'] ); return [ 'updated' => true ]; }
}
