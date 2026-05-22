<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Theme;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ThemeGetActiveAbility extends AbstractAbility {
	public function slug(): string { return 'theme-get-active'; }
	protected function meta(): array { return [ 'label' => __( 'Get active theme', 'mcp-for-wordpress' ), 'description' => __( 'Returns the active theme name, version, author, template/stylesheet.', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'name' => [ 'type' => 'string' ], 'version' => [ 'type' => 'string' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'switch_themes' ); }
	public function execute( array $input = [] ): array {
		$t = wp_get_theme();
		return [
			'name' => (string) $t->get( 'Name' ),
			'version' => (string) $t->get( 'Version' ),
			'author' => (string) $t->get( 'Author' ),
			'template' => (string) $t->get_template(),
			'stylesheet' => (string) $t->get_stylesheet(),
			'is_child' => $t->parent() ? true : false,
		];
	}
}
