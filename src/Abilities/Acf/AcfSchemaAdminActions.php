<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Acf;

use Tropk\Mcp\Acf\AcfRuntime;

/**
 * Shared admin-side actions for the four ACF schema entities (field
 * groups, post types, taxonomies, options pages). They all funnel through
 * ACF's `acf_*_internal_post_type` helpers — same code path the ACF admin
 * UI uses, so trash/restore semantics, active flag and duplicate work
 * identically to a click in wp-admin.
 */
trait AcfSchemaAdminActions {

	/**
	 * @param string $entity field-group | post-type | taxonomy | options-page
	 */
	private function activate_entity( string $entity, string $id ): array {
		AcfRuntime::require_active();
		if ( ! function_exists( 'acf_update_internal_post_type_active_status' ) ) {
			throw new \RuntimeException( 'ACF activate/deactivate requires ACF Pro 6.1+.' );
		}
		$ok = (bool) acf_update_internal_post_type_active_status( $id, true, AcfRuntime::internal_post_type( $entity ) );
		return [ 'activated' => $ok ];
	}

	private function deactivate_entity( string $entity, string $id ): array {
		AcfRuntime::require_active();
		if ( ! function_exists( 'acf_update_internal_post_type_active_status' ) ) {
			throw new \RuntimeException( 'ACF activate/deactivate requires ACF Pro 6.1+.' );
		}
		$ok = (bool) acf_update_internal_post_type_active_status( $id, false, AcfRuntime::internal_post_type( $entity ) );
		return [ 'deactivated' => $ok ];
	}

	private function trash_entity( string $entity, string $id ): array {
		AcfRuntime::require_active();
		$fn = 'field-group' === $entity ? 'acf_trash_field_group' : 'acf_trash_internal_post_type';
		if ( ! function_exists( $fn ) ) {
			throw new \RuntimeException( 'ACF trash requires ACF Pro 6.1+ (or ACF 6.0+ for field groups).' );
		}
		$ok = 'field-group' === $entity
			? (bool) acf_trash_field_group( $id )
			: (bool) acf_trash_internal_post_type( $id, AcfRuntime::internal_post_type( $entity ) );
		return [ 'trashed' => $ok ];
	}

	private function untrash_entity( string $entity, string $id ): array {
		AcfRuntime::require_active();
		$fn = 'field-group' === $entity ? 'acf_untrash_field_group' : 'acf_untrash_internal_post_type';
		if ( ! function_exists( $fn ) ) {
			throw new \RuntimeException( 'ACF untrash requires ACF Pro 6.1+.' );
		}
		$ok = 'field-group' === $entity
			? (bool) acf_untrash_field_group( $id )
			: (bool) acf_untrash_internal_post_type( $id, AcfRuntime::internal_post_type( $entity ) );
		return [ 'restored' => $ok ];
	}

	private function duplicate_entity( string $entity, string $id ): array {
		AcfRuntime::require_active();
		if ( 'field-group' === $entity ) {
			if ( ! function_exists( 'acf_duplicate_field_group' ) ) {
				throw new \RuntimeException( 'acf_duplicate_field_group is unavailable on this ACF version.' );
			}
			$duplicate = acf_duplicate_field_group( $id );
		} else {
			if ( ! function_exists( 'acf_duplicate_internal_post_type' ) ) {
				throw new \RuntimeException( 'acf_duplicate_internal_post_type requires ACF Pro 6.1+.' );
			}
			$duplicate = acf_duplicate_internal_post_type( $id, 0, AcfRuntime::internal_post_type( $entity ) );
		}
		if ( ! is_array( $duplicate ) ) {
			throw new \RuntimeException( sprintf( 'ACF could not duplicate %s "%s".', $entity, $id ) );
		}
		return [ 'duplicated' => true, 'entity' => $duplicate ];
	}

	/**
	 * Export as PHP source code (matches the "Generate PHP" admin button).
	 * Returns a string of `acf_add_local_field_group(...)` calls (or the
	 * post-type / taxonomy equivalent).
	 */
	private function export_entity_as_php( string $entity, string $id ): array {
		AcfRuntime::require_active();
		if ( 'field-group' === $entity ) {
			$post = acf_get_field_group( $id );
		} elseif ( 'post-type' === $entity ) {
			$post = acf_get_post_type( $id );
		} elseif ( 'taxonomy' === $entity ) {
			$post = acf_get_taxonomy( $id );
		} else {
			throw new \InvalidArgumentException( sprintf( 'Cannot export entity "%s" as PHP.', $entity ) );
		}
		if ( ! is_array( $post ) ) {
			throw new \RuntimeException( sprintf( 'ACF %s "%s" not found.', $entity, $id ) );
		}
		if ( ! function_exists( 'acf_export_internal_post_type_as_php' ) ) {
			throw new \RuntimeException( 'acf_export_internal_post_type_as_php requires ACF Pro 6.1+.' );
		}
		$php = acf_export_internal_post_type_as_php( $post, AcfRuntime::internal_post_type( $entity ) );
		if ( false === $php ) {
			throw new \RuntimeException( 'ACF refused the export.' );
		}
		return [ 'php' => (string) $php, 'entity_id' => $id ];
	}

	/**
	 * Import the array shape produced by the ACF admin "Export → JSON"
	 * download. ACF prepares + persists the array via its instance.
	 *
	 * @param array<string, mixed> $payload
	 */
	private function import_entity( string $entity, array $payload ): array {
		AcfRuntime::require_active();
		if ( 'field-group' === $entity ) {
			if ( ! function_exists( 'acf_import_field_group' ) ) {
				throw new \RuntimeException( 'acf_import_field_group is unavailable on this ACF version.' );
			}
			$imported = acf_import_field_group( $payload );
		} else {
			if ( ! function_exists( 'acf_import_internal_post_type' ) ) {
				throw new \RuntimeException( 'acf_import_internal_post_type requires ACF Pro 6.1+.' );
			}
			$imported = acf_import_internal_post_type( $payload, AcfRuntime::internal_post_type( $entity ) );
		}
		if ( ! is_array( $imported ) ) {
			throw new \RuntimeException( 'ACF refused the import.' );
		}
		return [ 'imported' => true, 'entity' => $imported ];
	}
}
