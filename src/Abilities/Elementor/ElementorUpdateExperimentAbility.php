<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorUpdateExperimentAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-update-experiment'; }
	protected function meta(): array { return [ 'label' => __( 'Update an Elementor experiment', 'mcp-for-wordpress' ), 'description' => __( "Sets an Elementor experiment to active, inactive, or default. Pass reset=true to clear any saved state. Refuses to activate an experiment whose dependencies aren't active.", 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'name' ], 'properties' => [
		'name'  => [ 'type' => 'string', 'minLength' => 1 ],
		'state' => [ 'type' => 'string', 'enum' => [ 'default', 'active', 'inactive' ], 'default' => 'default' ],
		'reset' => [ 'type' => 'boolean', 'default' => false ],
	] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'updated' => [ 'type' => 'boolean' ], 'saved_state' => [ 'type' => 'string' ], 'effective_state' => [ 'type' => 'string' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance->experiments ) ) {
			throw new \RuntimeException( 'Elementor experiments manager is not available.' );
		}
		$mgr = \Elementor\Plugin::$instance->experiments;
		$name = (string) $input['name'];
		$feature = $mgr->get_features( $name );
		if ( empty( $feature ) ) throw new \RuntimeException( 'Experiment not found: ' . $name );
		if ( empty( $feature['mutable'] ) ) throw new \RuntimeException( 'Experiment is not mutable: ' . $name );
		$state = (string) ( $input['state'] ?? 'default' );
		$reset = ! empty( $input['reset'] );
		$default_state = (string) ( $feature['default'] ?? 'default' );
		if ( 'active' === $state && ! empty( $feature['dependencies'] ) && is_array( $feature['dependencies'] ) ) {
			foreach ( $feature['dependencies'] as $d ) {
				$dn = is_object( $d ) && method_exists( $d, 'get_name' ) ? (string) $d->get_name() : ( is_string( $d ) ? $d : '' );
				if ( '' === $dn ) continue;
				$df = $mgr->get_features( $dn );
				$dkey = $mgr->get_feature_option_key( $dn );
				$ds = get_option( $dkey ); $ds = $ds ? (string) $ds : 'default';
				$dd = (string) ( $df['default'] ?? 'default' );
				$de = 'default' === $ds ? $dd : $ds;
				if ( 'active' !== $de ) throw new \RuntimeException( 'Dependency not active: ' . $dn );
			}
		}
		$option_key = $mgr->get_feature_option_key( $name );
		if ( $reset || 'default' === $state ) {
			delete_option( $option_key );
			$saved = 'default'; $effective = $default_state;
		} else {
			update_option( $option_key, $state );
			$saved = $state; $effective = $state;
		}
		return [ 'updated' => true, 'name' => $name, 'saved_state' => $saved, 'effective_state' => $effective, 'is_active' => 'active' === $effective ];
	}
}
