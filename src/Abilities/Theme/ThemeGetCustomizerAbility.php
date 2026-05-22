<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Theme;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ThemeGetCustomizerAbility extends AbstractAbility {
	public function slug(): string { return 'theme-get-customizer'; }
	protected function meta(): array { return [ 'label' => __( 'Get theme mods (Customizer)', 'mcp-for-wordpress' ), 'description' => __( "Returns the active theme's get_theme_mods() values.", 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'mods' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_theme_options' ); }
	public function execute( array $input = [] ): array { return [ 'mods' => (array) get_theme_mods() ]; }
}
