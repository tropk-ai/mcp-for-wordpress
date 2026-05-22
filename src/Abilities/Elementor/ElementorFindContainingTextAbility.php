<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorFindContainingTextAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-find-widgets-containing-text'; }
	protected function meta(): array { return [ 'label' => __( 'Find widgets containing text', 'mcp-for-wordpress' ), 'description' => __( 'Returns widgets whose snippet contains the supplied substring (case-insensitive).', 'mcp-for-wordpress' ), 'readonly' => true ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id', 'text' ], 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'text' => [ 'type' => 'string' ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'count' => [ 'type' => 'integer' ], 'widgets' => [ 'type' => 'array' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ); }
	public function execute( array $input = [] ): array {
		$needle = mb_strtolower( (string) $input['text'] );
		$all    = ElementorPage::load( (int) $input['post_id'] )->widgets();
		$found  = array_values( array_filter( $all, static function ( $w ) use ( $needle ) {
			return ! empty( $w['snippet'] ) && false !== mb_strpos( mb_strtolower( (string) $w['snippet'] ), $needle );
		} ) );
		return [ 'count' => count( $found ), 'widgets' => $found ];
	}
}
