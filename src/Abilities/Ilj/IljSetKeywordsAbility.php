<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Ilj;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Ilj\IljClient;

final class IljSetKeywordsAbility implements Ability {

	public function slug(): string {
		return 'ilj-set-keywords';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Set Internal Link Juicer keywords', 'mcp-for-wordpress' ),
			'description'         => __( 'Replaces ilj_linkdefinition for a post with the supplied keyword list and triggers an ILJ reindex via wp_update_post. Snapshots the post first.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'post_id', 'keywords' ],
				'properties'           => [
					'post_id'  => [ 'type' => 'integer', 'minimum' => 1 ],
					'keywords' => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
					],
					'dry_run'  => [ 'type' => 'boolean', 'default' => false ],
				],
			],
			'output_schema'       => [
				'$schema'    => 'https://json-schema.org/draft/2020-12/schema',
				'type'       => 'object',
				'properties' => [
					'updated'     => [ 'type' => 'boolean' ],
					'dry_run'     => [ 'type' => 'boolean' ],
					'post_id'     => [ 'type' => 'integer' ],
					'keywords'    => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'snapshot_id' => [ 'type' => [ 'string', 'null' ] ],
				],
				'required'   => [ 'updated', 'dry_run', 'post_id', 'keywords' ],
			],
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations' => [
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				],
				'show_in_rest' => true,
			],
		];
	}

	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['post_id'] ?? 0 );
		if ( $id <= 0 ) {
			return false;
		}
		return current_user_can( 'mcp_invoke_destructive_tools' ) && current_user_can( 'edit_post', $id );
	}

	public function execute( array $input ): array {
		$post_id  = (int) $input['post_id'];
		$keywords = (array) ( $input['keywords'] ?? [] );
		$dry_run  = (bool) ( $input['dry_run'] ?? false );

		if ( ! IljClient::is_active() ) {
			throw new \RuntimeException( 'Internal Link Juicer is not active on this site.' );
		}

		if ( $dry_run ) {
			$normalized = array_values( array_unique( array_filter( array_map(
				static fn( $kw ) => is_string( $kw ) ? trim( $kw ) : '',
				$keywords
			), static fn( $kw ) => '' !== $kw ) ) );
			return [
				'updated'     => false,
				'dry_run'     => true,
				'post_id'     => $post_id,
				'keywords'    => $normalized,
				'snapshot_id' => null,
			];
		}

		$snapshot    = ( new SnapshotManager() )->snapshot_post( $post_id, 'ilj-set-keywords' );
		$snapshot_id = $snapshot['snapshot_id'];

		$saved = ( new IljClient() )->set_keywords( $post_id, array_map( 'strval', $keywords ) );

		return [
			'updated'     => true,
			'dry_run'     => false,
			'post_id'     => $post_id,
			'keywords'    => $saved,
			'snapshot_id' => $snapshot_id,
		];
	}
}
