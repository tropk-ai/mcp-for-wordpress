<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities;

/**
 * Shared base for self-registering ability classes. Subclasses only need
 * to declare slug(), describe(), schema arrays, an execute method, and
 * an authorize method; the boilerplate ("$schema", category injection,
 * annotation defaults, callback wiring) lives here.
 */
abstract class AbstractAbility implements Ability {

	abstract public function slug(): string;

	/**
	 * @return array{label:string, description:string, readonly?:bool, destructive?:bool, idempotent?:bool, openWorldHint?:bool}
	 */
	abstract protected function meta(): array;

	/**
	 * @return array<string, mixed>
	 */
	abstract protected function input_schema(): array;

	/**
	 * @return array<string, mixed>
	 */
	abstract protected function output_schema(): array;

	/**
	 * @param array<string, mixed> $input
	 * @return mixed
	 */
	abstract public function execute( array $input = [] );

	abstract public function authorize( array $input = [] ): bool;

	final public function definition(): array {
		$meta = $this->meta();
		return [
			'label'               => (string) $meta['label'],
			'description'         => (string) $meta['description'],
			'category'            => AbilityRegistrar::CATEGORY,
			'input_schema'        => $this->normalize_schema( $this->input_schema() ),
			'output_schema'       => $this->normalize_schema( $this->output_schema() ),
			'execute_callback'    => [ $this, 'execute' ],
			'permission_callback' => [ $this, 'authorize' ],
			'meta'                => [
				'annotations'  => array_filter(
					[
						'readonly'      => $meta['readonly']      ?? false,
						'destructive'   => $meta['destructive']   ?? false,
						'idempotent'    => $meta['idempotent']    ?? false,
						'openWorldHint' => $meta['openWorldHint'] ?? false,
					],
					static fn( $v ) => null !== $v
				),
				'show_in_rest' => true,
			],
		];
	}

	/**
	 * @param array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	final protected function normalize_schema( array $schema ): array {
		if ( ! isset( $schema['$schema'] ) ) {
			$schema['$schema'] = 'https://json-schema.org/draft/2020-12/schema';
		}
		if ( ! isset( $schema['type'] ) ) {
			$schema['type'] = 'object';
		}
		return $schema;
	}
}
