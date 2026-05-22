<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorListFormSubmissionsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-form-submissions'; }
	protected function meta(): array { return [ 'label' => __( 'List Elementor Pro form submissions', 'mcp-for-wordpress' ), 'description' => __( 'Lists rows from the Elementor Pro form submissions table ({prefix}e_submissions). Supports optional filters and include_values to join {prefix}e_submissions_values. Returns an empty list if Elementor Pro Forms is not installed.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'properties' => [
		'form_id'   => [ 'type' => 'string' ],
		'post_id'   => [ 'type' => 'integer' ],
		'user_id'   => [ 'type' => 'integer' ],
		'status'    => [ 'type' => 'string' ],
		'limit'     => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ],
		'offset'    => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0 ],
		'include_values' => [ 'type' => 'boolean', 'default' => false ],
	] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'submissions' => [ 'type' => 'array' ], 'total' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'e_submissions';
		$exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB
		if ( $exists !== $table ) return [ 'submissions' => [], 'total' => 0, 'message' => 'Elementor Pro Forms submissions table not found.' ];
		$where = [ '1=1' ];
		$params = [];
		if ( ! empty( $input['form_id'] ) ) { $where[] = 'form_id = %s'; $params[] = (string) $input['form_id']; }
		if ( ! empty( $input['post_id'] ) ) { $where[] = 'post_id = %d'; $params[] = (int) $input['post_id']; }
		if ( ! empty( $input['user_id'] ) ) { $where[] = 'user_id = %d'; $params[] = (int) $input['user_id']; }
		if ( ! empty( $input['status'] ) ) { $where[] = 'status = %s'; $params[] = (string) $input['status']; }
		$limit = (int) ( $input['limit'] ?? 50 );
		$offset = (int) ( $input['offset'] ?? 0 );
		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$sel_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$prep_count = $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql; // phpcs:ignore WordPress.DB
		$total = (int) $wpdb->get_var( $prep_count ); // phpcs:ignore WordPress.DB
		$prep_sel = $wpdb->prepare( $sel_sql, array_merge( $params, [ $limit, $offset ] ) ); // phpcs:ignore WordPress.DB
		$rows = (array) $wpdb->get_results( $prep_sel, ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( ! empty( $input['include_values'] ) && $rows ) {
			$vtable = $wpdb->prefix . 'e_submissions_values';
			if ( $vtable === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $vtable ) ) ) { // phpcs:ignore WordPress.DB
				foreach ( $rows as &$r ) {
					$sid = (int) ( $r['id'] ?? 0 );
					$vals = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$vtable} WHERE submission_id = %d", $sid ), ARRAY_A ); // phpcs:ignore WordPress.DB
					$r['values'] = is_array( $vals ) ? $vals : [];
				}
				unset( $r );
			}
		}
		return [ 'submissions' => $rows, 'total' => $total ];
	}
}
