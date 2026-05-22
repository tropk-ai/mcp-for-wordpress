<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Theme;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ThemeSwitchAbility extends AbstractAbility {
	public function slug(): string { return 'theme-switch'; }
	protected function meta(): array { return [ 'label' => __( 'Switch active theme', 'mcp-for-wordpress' ), 'description' => __( "Activates a different theme by stylesheet slug. Use with care — the front-end appearance changes immediately.", 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'stylesheet' ], 'properties' => [ 'stylesheet' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'switched' => [ 'type' => 'boolean' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'switch_themes' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		switch_theme( (string) $input['stylesheet'] );
		return [ 'switched' => true ];
	}
}
