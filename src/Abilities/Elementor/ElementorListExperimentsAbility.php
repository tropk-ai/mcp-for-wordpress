<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorListExperimentsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-experiments'; }
	protected function meta(): array { return [ 'label' => __( 'List Elementor experiments', 'mcp-for-wordpress' ), 'description' => __( 'Returns Elementor experiments with their default, saved, and effective state, plus mutability and dependency info.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => new \stdClass() ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'experiments' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance->experiments ) ) {
			throw new \RuntimeException( 'Elementor experiments manager is not available.' );
		}
		$mgr = \Elementor\Plugin::$instance->experiments;
		$features = $mgr->get_features();
		$out = [];
		foreach ( (array) $features as $name => $feature ) {
			$option_key = $mgr->get_feature_option_key( $name );
			$saved = get_option( $option_key );
			$saved = $saved ? (string) $saved : 'default';
			$default = (string) ( $feature['default'] ?? 'default' );
			$effective = 'default' === $saved ? $default : $saved;
			$deps = [];
			if ( ! empty( $feature['dependencies'] ) && is_array( $feature['dependencies'] ) ) {
				foreach ( $feature['dependencies'] as $d ) {
					if ( is_object( $d ) && method_exists( $d, 'get_name' ) ) $deps[] = (string) $d->get_name();
					elseif ( is_string( $d ) ) $deps[] = $d;
				}
			}
			$out[] = [
				'name'            => (string) ( $feature['name'] ?? $name ),
				'title'           => isset( $feature['title'] ) ? (string) wp_strip_all_tags( (string) $feature['title'] ) : '',
				'description'     => isset( $feature['description'] ) ? (string) wp_strip_all_tags( (string) $feature['description'] ) : '',
				'release_status'  => (string) ( $feature['release_status'] ?? '' ),
				'mutable'         => ! empty( $feature['mutable'] ),
				'default_state'   => $default,
				'saved_state'     => $saved,
				'effective_state' => $effective,
				'is_active'       => 'active' === $effective,
				'dependencies'    => $deps,
			];
		}
		return [ 'experiments' => $out, 'total' => count( $out ) ];
	}
}
