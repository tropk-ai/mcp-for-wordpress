<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorGetPageOutlineAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-get-page-outline'; }
	protected function meta(): array { return [
		'label' => __( 'Get Elementor page outline', 'mcp-for-wordpress' ),
		'description' => __( 'Returns a compact tree outline of an Elementor page. Atomic widgets are flagged but never decoded.', 'mcp-for-wordpress' ),
		'readonly' => true, 'idempotent' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id' ],
		'properties'           => [
			'post_id'   => [ 'type' => 'integer', 'minimum' => 1 ],
			'max_bytes' => [ 'type' => 'integer', 'minimum' => 256, 'maximum' => 16384, 'default' => 2048 ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'is_empty' => [ 'type' => 'boolean' ], 'outline' => [ 'type' => 'string' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		return [
			'post_id'  => $page->post_id(),
			'is_empty' => $page->is_empty(),
			'outline'  => $page->outline( (int) ( $input['max_bytes'] ?? 2048 ) ),
		];
	}
}
