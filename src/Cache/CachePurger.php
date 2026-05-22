<?php
declare(strict_types=1);

namespace Tropk\Mcp\Cache;

/**
 * Auto-detects installed cache plugins and dispatches purge calls
 * through their public APIs. We never reach into a cache provider's
 * internals — only documented hooks/functions are called.
 */
final class CachePurger {

	public const PROVIDERS = [ 'rocket', 'litespeed', 'w3tc', 'super', 'cache_enabler', 'autoptimize' ];

	/**
	 * @return array<string, bool>
	 */
	public function detect(): array {
		return [
			'rocket'        => function_exists( 'rocket_clean_post' ) || function_exists( 'rocket_clean_domain' ),
			'litespeed'     => class_exists( '\\LiteSpeed\\Purge' ) || defined( 'LSCWP_V' ),
			'w3tc'          => function_exists( 'w3tc_flush_post' ) || function_exists( 'w3tc_flush_all' ),
			'super'         => function_exists( 'wp_cache_clear_cache' ) || function_exists( 'wp_cache_post_change' ),
			'cache_enabler' => class_exists( '\\Cache_Enabler' ),
			'autoptimize'   => class_exists( '\\autoptimizeCache' ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function purge_post( int $post_id, ?string $provider = null ): array {
		$detected = $this->detect();
		$targets  = $this->targets( $detected, $provider );
		$results  = [];

		foreach ( $targets as $name ) {
			$results[ $name ] = $this->purge_single_post( $name, $post_id );
		}

		clean_post_cache( $post_id );

		return [
			'scope'    => 'post',
			'post_id'  => $post_id,
			'results'  => $results,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function purge_all( ?string $provider = null ): array {
		$detected = $this->detect();
		$targets  = $this->targets( $detected, $provider );
		$results  = [];

		foreach ( $targets as $name ) {
			$results[ $name ] = $this->purge_all_for( $name );
		}

		return [
			'scope'   => 'all',
			'results' => $results,
		];
	}

	/**
	 * @param array<string, bool> $detected
	 * @return array<int, string>
	 */
	private function targets( array $detected, ?string $provider ): array {
		if ( null === $provider || 'auto' === $provider ) {
			return array_keys( array_filter( $detected ) );
		}
		if ( ! in_array( $provider, self::PROVIDERS, true ) ) {
			throw new \RuntimeException( sprintf( 'Unknown cache provider "%s".', $provider ) );
		}
		if ( empty( $detected[ $provider ] ) ) {
			throw new \RuntimeException( sprintf( 'Cache provider "%s" is not active.', $provider ) );
		}
		return [ $provider ];
	}

	private function purge_single_post( string $provider, int $post_id ): bool {
		switch ( $provider ) {
			case 'rocket':
				if ( function_exists( 'rocket_clean_post' ) ) {
					rocket_clean_post( $post_id );
					return true;
				}
				return false;
			case 'litespeed':
				do_action( 'litespeed_purge_post', $post_id );
				return true;
			case 'w3tc':
				if ( function_exists( 'w3tc_flush_post' ) ) {
					w3tc_flush_post( $post_id );
					return true;
				}
				return false;
			case 'super':
				if ( function_exists( 'wp_cache_post_change' ) ) {
					wp_cache_post_change( $post_id );
					return true;
				}
				return false;
			case 'cache_enabler':
				if ( method_exists( '\\Cache_Enabler', 'clear_page_cache_by_post' ) ) {
					\Cache_Enabler::clear_page_cache_by_post( $post_id );
					return true;
				}
				return false;
			case 'autoptimize':
				if ( method_exists( '\\autoptimizeCache', 'clearall' ) ) {
					\autoptimizeCache::clearall();
					return true;
				}
				return false;
		}
		return false;
	}

	private function purge_all_for( string $provider ): bool {
		switch ( $provider ) {
			case 'rocket':
				if ( function_exists( 'rocket_clean_domain' ) ) {
					rocket_clean_domain();
					return true;
				}
				return false;
			case 'litespeed':
				do_action( 'litespeed_purge_all' );
				return true;
			case 'w3tc':
				if ( function_exists( 'w3tc_flush_all' ) ) {
					w3tc_flush_all();
					return true;
				}
				return false;
			case 'super':
				if ( function_exists( 'wp_cache_clear_cache' ) ) {
					wp_cache_clear_cache();
					return true;
				}
				return false;
			case 'cache_enabler':
				if ( method_exists( '\\Cache_Enabler', 'clear_complete_cache' ) ) {
					\Cache_Enabler::clear_complete_cache();
					return true;
				}
				return false;
			case 'autoptimize':
				if ( method_exists( '\\autoptimizeCache', 'clearall' ) ) {
					\autoptimizeCache::clearall();
					return true;
				}
				return false;
		}
		return false;
	}
}
