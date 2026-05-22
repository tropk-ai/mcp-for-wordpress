<?php
declare(strict_types=1);

namespace Tropk\Mcp\Abilities\Elementor;

use Tropk\Mcp\Abilities\AbstractAbility;

final class ElementorGetOfficialWidgetCatalogAbility extends AbstractAbility {
	private const TRANSIENT = 'tropk_mcp_elem_widget_catalog_v1';
	private const URL       = 'https://elementor.com/widgets';

	public function slug(): string { return 'elementor-get-official-widget-catalog'; }
	protected function meta(): array { return [
		'label'       => __( 'Fetch official Elementor widget catalog', 'mcp-for-wordpress' ),
		'description' => __( 'Fetches the Basic / Pro / Theme / WooCommerce widget catalog from elementor.com/widgets (cached 12 hours).', 'mcp-for-wordpress' ),
		'readonly'    => true,
	]; }
	protected function input_schema(): array { return [
		'additionalProperties' => false,
		'properties'           => [
			'force_refresh' => [ 'type' => 'boolean', 'default' => false ],
		],
	]; }
	protected function output_schema(): array { return [ 'properties' => [ 'catalog' => [ 'type' => 'object' ] ] ]; }
	public function authorize( array $input = [] ): bool { return current_user_can( 'edit_posts' ); }
	public function execute( array $input = [] ): array {
		if ( empty( $input['force_refresh'] ) ) {
			$cached = get_transient( self::TRANSIENT );
			if ( is_array( $cached ) && ! empty( $cached['categories'] ) ) {
				return [ 'catalog' => $cached ];
			}
		}
		$res = wp_remote_get( self::URL, [ 'timeout' => 20, 'redirection' => 5, 'headers' => [ 'Accept' => 'text/html' ] ] );
		if ( is_wp_error( $res ) ) {
			throw new \RuntimeException( $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( sprintf( 'Widget catalog fetch failed with HTTP %d.', $code ) );
		}
		$html = (string) wp_remote_retrieve_body( $res );
		if ( '' === trim( $html ) ) {
			throw new \RuntimeException( 'Widget catalog response was empty.' );
		}
		$h2s = [
			'Basic Widgets'       => 'basic',
			'Pro Widgets'         => 'pro',
			'Theme Elements'      => 'theme',
			'WooCommerce Widgets' => 'woocommerce',
		];
		$labels = [
			'basic' => 'Basic Widgets', 'pro' => 'Pro Widgets',
			'theme' => 'Theme Elements', 'woocommerce' => 'WooCommerce Widgets',
		];
		$cats = [ 'basic' => [], 'pro' => [], 'theme' => [], 'woocommerce' => [] ];
		if ( class_exists( 'DOMDocument' ) ) {
			$dom = new \DOMDocument();
			libxml_use_internal_errors( true );
			$dom->loadHTML( $html, LIBXML_NOERROR | LIBXML_NOWARNING );
			libxml_clear_errors();
			$xp = new \DOMXPath( $dom );
			$nodes = $xp->query( '//h2 | //h3' );
			$current = '';
			if ( $nodes ) {
				foreach ( $nodes as $n ) {
					$text = trim( (string) preg_replace( '/\s+/', ' ', (string) $n->textContent ) );
					if ( '' === $text ) continue;
					if ( 'h2' === strtolower( $n->nodeName ) ) {
						$current = $h2s[ $text ] ?? '';
						continue;
					}
					if ( '' === $current ) continue;
					$cats[ $current ][ $text ] = [
						'name'           => $text,
						'slug'           => self::slugify( $text ),
						'category'       => $current,
						'category_label' => $labels[ $current ],
					];
				}
			}
		}
		$normalized = [];
		$total = 0;
		foreach ( $cats as $k => $v ) {
			$normalized[ $k ] = array_values( $v );
			$total += count( $normalized[ $k ] );
		}
		$catalog = [
			'catalog_source_url' => self::URL,
			'fetched_at'         => gmdate( 'c' ),
			'total_widgets'      => $total,
			'categories'         => $normalized,
		];
		set_transient( self::TRANSIENT, $catalog, 12 * HOUR_IN_SECONDS );
		return [ 'catalog' => $catalog ];
	}
	private static function slugify( string $s ): string {
		$slug = strtolower( trim( $s ) );
		$slug = (string) preg_replace( '/[^a-z0-9]+/', '-', $slug );
		return trim( $slug, '-' );
	}
}
