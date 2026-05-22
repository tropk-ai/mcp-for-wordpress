<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorUpdateMaintenanceModeAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-update-maintenance-mode'; }
	protected function meta(): array { return [
		'label'       => __( 'Update Elementor Maintenance Mode', 'mcp-for-wordpress' ),
		'description' => __( 'Enables or disables Elementor maintenance/coming-soon mode and configures the template + exclusion rules.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [
			'enabled'       => [ 'type' => 'boolean', 'default' => true ],
			'mode'          => [ 'type' => 'string', 'enum' => [ 'maintenance', 'coming_soon' ] ],
			'template_id'   => [ 'type' => 'integer', 'minimum' => 1 ],
			'exclude_mode'  => [ 'type' => 'string', 'enum' => [ 'none', 'logged_in', 'custom' ] ],
			'exclude_roles' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'enabled' => [ 'type' => 'boolean' ], 'mode' => [ 'type' => 'string' ],
		'template_id' => [ 'type' => 'integer' ], 'exclude_mode' => [ 'type' => 'string' ],
		'exclude_roles' => [ 'type' => 'array' ],
	] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		if ( ! class_exists( '\\Elementor\\Maintenance_Mode' ) ) {
			throw new \RuntimeException( 'Elementor Maintenance Mode is not available.' );
		}
		$enabled = ! array_key_exists( 'enabled', $input ) || ! empty( $input['enabled'] );
		if ( ! $enabled ) {
			\Elementor\Maintenance_Mode::set( 'mode', '' );
			\Elementor\Maintenance_Mode::set( 'template_id', 0 );
			\Elementor\Maintenance_Mode::set( 'exclude_mode', '' );
			\Elementor\Maintenance_Mode::set( 'exclude_roles', [] );
			return [ 'enabled' => false, 'mode' => '', 'template_id' => 0, 'exclude_mode' => '', 'exclude_roles' => [] ];
		}
		$mode = (string) ( $input['mode'] ?? '' );
		$tpl  = (int) ( $input['template_id'] ?? 0 );
		if ( '' === $mode || $tpl <= 0 || ! get_post( $tpl ) ) {
			throw new \RuntimeException( 'mode and a valid template_id are required to enable maintenance mode.' );
		}
		$exclude_mode = (string) ( $input['exclude_mode'] ?? '' );
		if ( 'none' === $exclude_mode ) $exclude_mode = '';
		$exclude_roles = [];
		if ( 'custom' === $exclude_mode ) {
			$in = (array) ( $input['exclude_roles'] ?? [] );
			if ( empty( $in ) ) {
				throw new \RuntimeException( 'exclude_roles is required when exclude_mode is custom.' );
			}
			$exclude_roles = array_values( array_map( 'sanitize_text_field', $in ) );
		}
		\Elementor\Maintenance_Mode::set( 'mode', $mode );
		\Elementor\Maintenance_Mode::set( 'template_id', $tpl );
		\Elementor\Maintenance_Mode::set( 'exclude_mode', $exclude_mode );
		\Elementor\Maintenance_Mode::set( 'exclude_roles', $exclude_roles );
		return [
			'enabled'       => true,
			'mode'          => $mode,
			'template_id'   => $tpl,
			'exclude_mode'  => $exclude_mode,
			'exclude_roles' => $exclude_roles,
		];
	}
}
