<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorRuntime;
final class ElementorListVariablesAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-variables'; }
	protected function meta(): array { return [
		'label'       => __( 'List Elementor V4 design-system variables', 'mcp-for-wordpress' ),
		'description' => __( 'Returns the V4 Variables registered on the active Kit (Colors + Fonts). Each entry: {id, type, label, value, order, deleted?, deleted_at?}. Use include_deleted=true to also list soft-deleted variables.', 'mcp-for-wordpress' ),
		'readonly'    => true,
		'idempotent'  => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [
			'type'            => [ 'type' => 'string', 'enum' => [ 'global-color-variable', 'global-font-variable' ] ],
			'include_deleted' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [
		'properties' => [
			'variables' => [ 'type' => 'array' ],
			'watermark' => [ 'type' => [ 'integer', 'null' ] ],
			'count'     => [ 'type' => 'integer' ],
		],
	]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		$repo  = ElementorRuntime::require_variables();
		$db    = $repo->load();
		$items = (array) ( $db['data'] ?? [] );

		$type    = (string) ( $input['type'] ?? '' );
		$include = (bool)   ( $input['include_deleted'] ?? false );
		$out     = [];
		foreach ( $items as $id => $row ) {
			if ( ! $include && ! empty( $row['deleted'] ) ) continue;
			if ( '' !== $type && (string) ( $row['type'] ?? '' ) !== $type ) continue;
			$out[] = array_merge( [ 'id' => (string) $id ], (array) $row );
		}
		return [
			'variables' => $out,
			'watermark' => isset( $db['watermark'] ) ? (int) $db['watermark'] : null,
			'count'     => count( $out ),
		];
	}
}
