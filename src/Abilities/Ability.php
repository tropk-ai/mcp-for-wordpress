<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities;

interface Ability {

	public function slug(): string;

	/**
	 * Returns the array passed to wp_register_ability().
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array;
}
