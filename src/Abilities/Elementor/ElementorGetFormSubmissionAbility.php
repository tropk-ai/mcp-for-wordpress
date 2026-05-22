<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorGetFormSubmissionAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-form-submission'; }
	protected function meta(): array { return [ 'label' => __( 'Get an Elementor Pro form submission', 'mcp-for-wordpress' ), 'description' => __( 'Reads a single row from {prefix}e_submissions and (optionally) its field values from {prefix}e_submissions_values.', 'mcp-for-wordpress' ), 'readonly' => true, 'idempotent' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'id' ], 'properties' => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ], 'include_values' => [ 'type' => 'boolean', 'default' => true ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'submission' => [ 'type' => [ 'object', 'null' ] ], 'values' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ); }
	public function execute( array $input = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'e_submissions';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB
			throw new \RuntimeException( 'Elementor Pro Forms submissions table not found.' );
		}
		$id = (int) $input['id'];
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( empty( $row ) ) throw new \RuntimeException( 'Submission not found.' );
		$values = [];
		if ( ! empty( $input['include_values'] ) ) {
			$vtable = $wpdb->prefix . 'e_submissions_values';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $vtable ) ) === $vtable ) { // phpcs:ignore WordPress.DB
				$values = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$vtable} WHERE submission_id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
			}
		}
		return [ 'submission' => $row, 'values' => $values ];
	}
}
