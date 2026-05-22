<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorListDynamicTagsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-dynamic-tags'; }
	protected function meta(): array { return [ 'label' => __( 'List Elementor dynamic tags', 'mcp-for-wordpress' ), 'description' => __( 'Lists all available Elementor / Elementor Pro dynamic tags, optionally filtered by group (post, site, author, media, action, woocommerce, …).', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => [ 'group' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'tags' => [ 'type' => 'array' ], 'count' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) throw new \RuntimeException( 'Elementor is not loaded.' );
		$mgr = \Elementor\Plugin::$instance->dynamic_tags ?? null;
		if ( ! $mgr ) throw new \RuntimeException( 'Dynamic tags manager not available.' );
		$filter = (string) ( $input['group'] ?? '' );
		$tags = method_exists( $mgr, 'get_tags' ) ? (array) $mgr->get_tags() : [];
		$out = [];
		foreach ( $tags as $name => $info ) {
			if ( ! is_array( $info ) || empty( $info['instance'] ) ) continue;
			$inst = $info['instance'];
			$group = method_exists( $inst, 'get_group' ) ? (string) $inst->get_group() : '';
			if ( '' !== $filter && $group !== $filter ) continue;
			$out[] = [
				'name'       => (string) $name,
				'title'      => method_exists( $inst, 'get_title' ) ? (string) $inst->get_title() : (string) $name,
				'group'      => $group,
				'categories' => method_exists( $inst, 'get_categories' ) ? (array) $inst->get_categories() : [],
			];
		}
		return [ 'tags' => $out, 'count' => count( $out ) ];
	}
}
