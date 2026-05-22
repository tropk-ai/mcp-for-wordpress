<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorWordCountAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-word-count'; }
	protected function meta(): array { return [ 'label' => __( 'Count visible words on the page', 'mcp-for-wordpress' ), 'description' => __( 'Sums word counts in heading, text-editor, button and image-box widgets.', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) ;
	}
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$words = 0;
		foreach ( $page->widgets() as $w ) {
			if ( ! empty( $w["atomic"] ) ) continue;
			$n = $page->find_widget( (string) $w["id"] );
			foreach ( [ "title", "editor", "text", "title_text", "description_text", "subtitle", "header", "subheader" ] as $f ) {
				if ( isset( $n["settings"][ $f ] ) && is_string( $n["settings"][ $f ] ) ) {
					$words += str_word_count( wp_strip_all_tags( $n["settings"][ $f ] ) );
				}
			}
		}
		return [ "result" => [ "word_count" => $words ] ];
	}
}
