<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Ilj;

use Tropk\Mcp\Abilities\Ability;
use Tropk\Mcp\Abilities\AbilityRegistrar;
use Tropk\Mcp\Ilj\IljClient;

final class IljInspectIndexAbility implements Ability {

	public function slug(): string {
		return 'ilj-inspect-index';
	}

	public function definition(): array {
		return [
			'label'               => __( 'Inspect ILJ link index', 'mcp-for-wordpress' ),
			'description'         => __( 'Reports whether the {prefix}ilj_linkindex table exists, its columns, and its row count. Useful for confirming the schema before issuing custom queries.', 'mcp-for-wordpress' ),
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => [
				'$schema'              => 'https://json-schema.org/draft/2020-12/schema',
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => new \stdClass(),
			],
			'output_schema'       => [
				'$schema'    => 'https://json-schema.org/draft/2020-12/schema',
				'type'       => 'object',
				'properties' => [
					'ilj_active' => [ 'type' => 'boolean' ],
					'table'      => [ 'type' => 'string' ],
					'exists'     => [ 'type' => 'boolean' ],
					'columns'    => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'row_count'  => [ 'type' => 'integer' ],
				],
				'required'   => [ 'ilj_active', 'table', 'exists', 'columns', 'row_count' ],
			],
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations' => [ 'readonly' => true, 'idempotent' => true ],
				'show_in_rest' => true,
			],
		];
	}

	public function authorize(): bool {
		return current_user_can( 'manage_options' );
	}

	public function execute(): array {
		$client = new IljClient();
		return [
			'ilj_active' => IljClient::is_active(),
			'table'      => $client->table_name(),
			'exists'     => $client->table_exists(),
			'columns'    => $client->table_columns(),
			'row_count'  => $client->row_count(),
		];
	}
}
