<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorPatchDataAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-patch-data'; }
	protected function meta(): array { return [
		'label' => __( 'Patch Elementor data (find & replace JSON)', 'mcp-for-wordpress' ),
		'description' => __( 'Performs raw find-and-replace on the JSON-serialised Elementor data. Optionally treats the find pattern as a regex. Aborts when the result is invalid JSON.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'find', 'replace' ],
		'properties'           => [
			'post_id' => [ 'type' => 'integer', 'minimum' => 1 ],
			'find'    => [ 'type' => 'string', 'minLength' => 1 ],
			'replace' => [ 'type' => 'string' ],
			'regex'   => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [
		'updated' => [ 'type' => 'boolean' ], 'replacements' => [ 'type' => 'integer' ],
		'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
	] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) && current_user_can( 'mcp_invoke_destructive_tools' ); }
	public function execute( array $input = [] ): array {
		$id  = (int) $input['post_id'];
		$raw = get_post_meta( $id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			throw new \RuntimeException( 'No Elementor data found for this post.' );
		}
		$find    = (string) $input['find'];
		$replace = (string) $input['replace'];
		$count   = 0;
		if ( ! empty( $input['regex'] ) ) {
			$new = preg_replace( $find, $replace, $raw, -1, $count );
			if ( null === $new ) {
				throw new \RuntimeException( 'Invalid regex pattern.' );
			}
		} else {
			$new = str_replace( $find, $replace, $raw, $count );
		}
		if ( 0 === $count ) {
			return [ 'updated' => false, 'replacements' => 0, 'snapshot_id' => null ];
		}
		$decoded = json_decode( (string) $new, true );
		if ( ! is_array( $decoded ) ) {
			throw new \RuntimeException( 'Replacement would result in invalid JSON.' );
		}
		$snap = ( new SnapshotManager() )->snapshot_post( $id, 'elementor-patch-data' );
		update_post_meta( $id, '_elementor_data', wp_slash( (string) $new ) );
		ElementorPage::load( $id )->flush_css();
		return [ 'updated' => true, 'replacements' => (int) $count, 'snapshot_id' => $snap['snapshot_id'] ];
	}
}
