<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;
use Tropk\Mcp\Elementor\ElementorPage;

final class ElementorAddCustomJsAbility extends AbstractAbility {
	public function slug(): string { return 'elementor-add-custom-js'; }
	protected function meta(): array { return [
		'label'       => __( 'Add Elementor custom JavaScript', 'mcp-for-wordpress' ),
		'description' => __( 'Injects a JavaScript snippet into a page by appending an HTML widget wrapped in <script>. Optionally wraps in DOMContentLoaded.', 'mcp-for-wordpress' ),
		'destructive' => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'required'             => [ 'post_id', 'parent_id', 'js' ],
		'properties'           => [
			'post_id'        => [ 'type' => 'integer', 'minimum' => 1 ],
			'parent_id'      => [ 'type' => 'string', 'minLength' => 1 ],
			'js'             => [ 'type' => 'string' ],
			'wrap_dom_ready' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'post_id' => [ 'type' => 'integer' ], 'element_id' => [ 'type' => 'string' ] ] ]; }
	public function authorize( array $input = [] ): bool {
		$id = (int) ( $input['post_id'] ?? 0 );
		return $id > 0 && current_user_can( 'edit_post', $id ) && current_user_can( 'mcp_invoke_destructive_tools' );
	}
	public function execute( array $input = [] ): array {
		$post_id   = (int) $input['post_id'];
		$parent_id = (string) $input['parent_id'];
		$js        = (string) ( $input['js'] ?? '' );
		if ( ! ElementorPage::is_elementor_post( $post_id ) ) {
			throw new \RuntimeException( sprintf( 'Post %d is not an Elementor page.', $post_id ) );
		}
		$js = (string) preg_replace( '/<\/?script[^>]*>/i', '', $js );
		if ( ! empty( $input['wrap_dom_ready'] ) ) {
			$js = "document.addEventListener('DOMContentLoaded', function() {\n" . $js . "\n});";
		}
		$html = "<script>\n" . $js . "\n</script>";
		$new_id = self::random_id();
		$page = ElementorPage::load( $post_id );
		$data = $page->data();
		$inserted = self::insert( $data, $parent_id, [
			'id'         => $new_id,
			'elType'     => 'widget',
			'widgetType' => 'html',
			'settings'   => [ 'html' => $html ],
			'elements'   => [],
		] );
		if ( ! $inserted ) {
			throw new \RuntimeException( sprintf( 'Parent element "%s" not found.', $parent_id ) );
		}
		$encoded = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		update_post_meta( $post_id, '_elementor_data', wp_slash( (string) $encoded ) );
		delete_post_meta( $post_id, '_elementor_css' );
		return [ 'post_id' => $post_id, 'element_id' => $new_id ];
	}
	private static function insert( array &$nodes, string $parent_id, array $widget ): bool {
		foreach ( $nodes as &$n ) {
			if ( ! is_array( $n ) ) continue;
			if ( ( $n['id'] ?? '' ) === $parent_id ) {
				if ( ! isset( $n['elements'] ) || ! is_array( $n['elements'] ) ) $n['elements'] = [];
				$n['elements'][] = $widget;
				return true;
			}
			if ( isset( $n['elements'] ) && is_array( $n['elements'] ) ) {
				if ( self::insert( $n['elements'], $parent_id, $widget ) ) return true;
			}
		}
		unset( $n );
		return false;
	}
	private static function random_id(): string {
		try { return bin2hex( random_bytes( 4 ) ); } catch ( \Throwable $e ) { return substr( md5( uniqid( '', true ) ), 0, 8 ); }
	}
}
