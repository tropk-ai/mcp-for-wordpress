<?php
declare(strict_types=1);
namespace Tropk\Mcp\Abilities\Elementor;
use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Backup\SnapshotManager;
use Tropk\Mcp\Elementor\ElementorPage;
final class ElementorListAnimationsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-list-animations'; }
	protected function meta(): array { return [ 'label' => __( 'List widgets with entrance animation', 'mcp-for-wordpress' ), 'description' => __( 'Returns widgets that declare an _animation key (fadeInUp, bounce, etc).', 'mcp-for-wordpress' ), 'readonly' => true, 'destructive' => false ]; }
	protected function input_schema(): array { return [ 'additionalProperties' => false, 'required' => [ 'post_id' ], 'properties' => [ 'post_id' => [ 'type' => 'integer', 'minimum' => 1 ] ] ]; }
	protected function output_schema(): array { return [ 'properties' => [ 'result' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		return current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) ) ;
	}
	public function execute( array $input = [] ): array {
		$page = ElementorPage::load( (int) $input['post_id'] );
		$out = [];
		foreach ( $page->widgets() as $w ) {
			$n = $page->find_widget( (string) $w["id"] );
			$anim = (string) ( $n["settings"]["_animation"] ?? "" );
			if ( "" !== $anim && "none" !== $anim ) {
				$out[] = [ "id" => $w["id"], "animation" => $anim, "delay" => (int) ( $n["settings"]["_animation_delay"] ?? 0 ) ];
			}
		}
		return [ "result" => [ "animations" => $out ] ];
	}
}
