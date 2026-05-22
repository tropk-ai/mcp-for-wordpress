<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
final class ElementorDeleteFormSubmissionAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-delete-form-submission'; }
	protected function meta(): array { return [ 'label' => __( 'Delete an Elementor Pro form submission', 'mcp-for-wordpress' ), 'description' => __( 'Deletes a submission row from {prefix}e_submissions and (by default) its field values from {prefix}e_submissions_values.', 'mcp-for-wordpress' ), 'destructive' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'id' ], 'properties' => [ 'id' => [ 'type' => 'integer', 'minimum' => 1 ], 'delete_values' => [ 'type' => 'boolean', 'default' => true ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'deleted' => [ 'type' => 'boolean' ], 'values_deleted' => [ 'type' => 'integer' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'manage_options' ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'e_submissions';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB
			throw new \RuntimeException( 'Elementor Pro Forms submissions table not found.' );
		}
		$id = (int) $input['id'];
		$values_deleted = 0;
		if ( ! empty( $input['delete_values'] ) ) {
			$vtable = $wpdb->prefix . 'e_submissions_values';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $vtable ) ) === $vtable ) { // phpcs:ignore WordPress.DB
				$values_deleted = (int) $wpdb->delete( $vtable, [ 'submission_id' => $id ] ); // phpcs:ignore WordPress.DB
			}
		}
		$deleted = (int) $wpdb->delete( $table, [ 'id' => $id ] ); // phpcs:ignore WordPress.DB
		if ( ! $deleted ) throw new \RuntimeException( 'Submission not found or already deleted.' );
		return [ 'deleted' => true, 'id' => $id, 'values_deleted' => $values_deleted ];
	}
}
