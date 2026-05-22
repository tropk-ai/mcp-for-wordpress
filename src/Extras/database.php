<?php
/**
 * Database abilities for the Abilities API.
 *
 * Registers 4 abilities under the `db/*` namespace: list tables, get
 * table structure, preview rows, and execute a sandboxed SELECT-only
 * query. Writes (INSERT/UPDATE/DELETE/DDL) are refused outright — those
 * exist in dedicated content abilities elsewhere.
 *
 * @package Tropk\Mcp\Extras
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_abilities_api_init',
	static function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'tropk-db/list-tables',
			[
				'label'               => 'DB: list tables',
     'category'            => 'tropk-core',
				'description'         => 'List database tables that share the WordPress table prefix.',
				'input_schema'        => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false ],
				'execute_callback'    => static function (): array {
					global $wpdb;
					$tables = (array) $wpdb->get_col( 'SHOW TABLES' );
					$prefix = $wpdb->prefix;
					$ours   = array_values( array_filter( $tables, static fn( $t ) => str_starts_with( (string) $t, $prefix ) ) );
					return [ 'tables' => $ours, 'prefix' => $prefix, 'count' => count( $ours ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-db/get-table-structure',
			[
				'label'               => 'DB: get table structure',
     'category'            => 'tropk-core',
				'description'         => 'Returns the columns of a table (DESCRIBE).',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'table' ],
					'properties' => [ 'table' => [ 'type' => 'string' ] ],
				],
				'execute_callback'    => static function ( array $input ): array {
					global $wpdb;
					$table = (string) $input['table'];
					if ( ! str_starts_with( $table, $wpdb->prefix ) ) {
						throw new \RuntimeException( 'Table is outside the WordPress prefix.' );
					}
					$rows = (array) $wpdb->get_results( 'DESCRIBE `' . esc_sql( $table ) . '`', ARRAY_A );
					if ( empty( $rows ) ) {
						throw new \RuntimeException( 'Table not found or no permission.' );
					}
					return [ 'table' => $table, 'columns' => $rows ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-db/preview-table',
			[
				'label'               => 'DB: preview table',
     'category'            => 'tropk-core',
				'description'         => 'Returns the first N rows of a table for inspection.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'table' ],
					'properties' => [
						'table' => [ 'type' => 'string' ],
						'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 10 ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					global $wpdb;
					$table = (string) $input['table'];
					if ( ! str_starts_with( $table, $wpdb->prefix ) ) {
						throw new \RuntimeException( 'Table is outside the WordPress prefix.' );
					}
					$limit = (int) ( $input['limit'] ?? 10 );
					$rows  = (array) $wpdb->get_results( 'SELECT * FROM `' . esc_sql( $table ) . '` LIMIT ' . $limit, ARRAY_A );
					return [ 'table' => $table, 'rows' => $rows, 'count' => count( $rows ) ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);

		wp_register_ability(
			'tropk-db/execute-select',
			[
				'label'               => 'DB: run SELECT query',
     'category'            => 'tropk-core',
				'description'         => 'Execute a read-only SELECT query. Rejects any keyword other than SELECT, EXPLAIN, SHOW or DESCRIBE in the leading statement.',
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'query' ],
					'properties' => [
						'query' => [ 'type' => 'string' ],
						'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 100, 'description' => 'Hard cap appended via LIMIT when missing.' ],
					],
				],
				'execute_callback'    => static function ( array $input ): array {
					global $wpdb;
					$q = trim( (string) $input['query'] );
					if ( '' === $q ) {
						throw new \RuntimeException( 'Empty query.' );
					}
					if ( str_contains( $q, ';' ) && false !== strpos( rtrim( $q, ';' ), ';' ) ) {
						throw new \RuntimeException( 'Multiple statements are not allowed.' );
					}
					$first = strtoupper( strtok( $q, " \t\n\r" ) );
					if ( ! in_array( $first, [ 'SELECT', 'EXPLAIN', 'SHOW', 'DESCRIBE', 'DESC' ], true ) ) {
						throw new \RuntimeException( 'Only read-only statements are allowed (SELECT/EXPLAIN/SHOW/DESCRIBE).' );
					}
					$limit = (int) ( $input['limit'] ?? 100 );
					if ( 'SELECT' === $first && ! preg_match( '/\bLIMIT\s+\d+/i', $q ) ) {
						$q .= ' LIMIT ' . $limit;
					}
					$rows = $wpdb->get_results( $q, ARRAY_A );
					if ( null === $rows && $wpdb->last_error ) {
						throw new \RuntimeException( $wpdb->last_error );
					}
					return [ 'rows' => is_array( $rows ) ? $rows : [], 'count' => is_array( $rows ) ? count( $rows ) : 0 ];
				},
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'meta'                => [ 'annotations' => [ 'readonly' => true, 'idempotent' => true ] ],
			]
		);
	},
	20
);
