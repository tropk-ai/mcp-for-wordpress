<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorGetMaintenanceModeAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-maintenance-mode'; }
	protected function meta(): array { return [
		'label'       => __( 'Get Elementor Maintenance Mode settings', 'mcp-for-wordpress' ),
		'description' => __( 'Returns current Elementor maintenance / coming-soon settings (mode, template, exclude roles).', 'mcp-for-wordpress' ),
		'readonly'    => true,
	]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [
		'enabled'       => [ 'type' => 'boolean' ], 'mode' => [ 'type' => 'string' ],
		'template_id'   => [ 'type' => 'integer' ], 'exclude_mode' => [ 'type' => 'string' ],
		'exclude_roles' => [ 'type' => 'array' ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		if ( ! class_exists( '\\Elementor\\Maintenance_Mode' ) ) {
			throw new \RuntimeException( 'Elementor Maintenance Mode is not available.' );
		}
		$mode  = (string) \Elementor\Maintenance_Mode::get( 'mode' );
		$tpl   = (int) \Elementor\Maintenance_Mode::get( 'template_id' );
		$exm   = (string) \Elementor\Maintenance_Mode::get( 'exclude_mode', '' );
		$roles = \Elementor\Maintenance_Mode::get( 'exclude_roles', [] );
		return [
			'enabled'       => ( '' !== $mode && $tpl > 0 ),
			'mode'          => $mode,
			'template_id'   => $tpl,
			'exclude_mode'  => $exm,
			'exclude_roles' => is_array( $roles ) ? array_values( $roles ) : [],
		];
	}
}
