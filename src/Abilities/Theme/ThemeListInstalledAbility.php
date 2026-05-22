<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Theme;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ThemeListInstalledAbility extends AbstractAbility {
	public function slug(): string { return 'theme-list-installed'; }
	protected function meta(): array { return [ 'label' => __( 'List installed themes', 'mcp-for-wordpress' ), 'description' => __( 'Returns every theme on disk with name/version/active flag.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'themes' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'switch_themes' ); }
	public function execute( array $input = [] ): array {
		$out = [];
		$active = wp_get_theme()->get_stylesheet();
		foreach ( wp_get_themes() as $slug => $t ) {
			$out[] = [ 'slug' => (string) $slug, 'name' => (string) $t->get( 'Name' ), 'version' => (string) $t->get( 'Version' ), 'active' => $active === $slug ];
		}
		return [ 'themes' => $out ];
	}
}
