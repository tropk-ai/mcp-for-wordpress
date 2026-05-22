<?php
declare(strict_types=1);

namespace Tropk\Mcp\Security;

/**
 * Token-bucket rate limiter for MCP requests, backed by transients.
 * Three independent buckets are checked: per-IP, per-user, and per-tool
 * (when the destructiveHint annotation is true on the called ability).
 *
 * Limits are configurable via the tropk_mcp_rate_limits filter.
 */
final class RateLimiter {

	private const PREFIX = 'tropk_rl_';

	/** @var array<string, array{limit:int, window:int}> */
	private array $limits;

	public function __construct() {
		$this->limits = (array) apply_filters(
			'tropk_mcp_rate_limits',
			[
				'ip'          => [ 'limit' => 60,   'window' => MINUTE_IN_SECONDS ],
				'user'        => [ 'limit' => 1000, 'window' => HOUR_IN_SECONDS ],
				'destructive' => [ 'limit' => 10,   'window' => HOUR_IN_SECONDS ],
			]
		);
	}

	public function register(): void {
		add_filter( 'rest_pre_dispatch', [ $this, 'enforce_global' ], 6, 3 );
		add_action( 'mcp_adapter_tool_executing', [ $this, 'enforce_destructive' ], 5, 2 );
	}

	/**
	 * @param mixed            $result
	 * @param \WP_REST_Server  $server
	 * @param \WP_REST_Request $request
	 * @return mixed
	 */
	public function enforce_global( $result, $server, $request ) {
		if ( null !== $result ) {
			return $result;
		}
		$route = is_object( $request ) && method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		if ( '' === $route || ! preg_match( '#^/(tropk-mcp|mcp)/#', $route ) ) {
			return $result;
		}

		$ip = $this->client_ip();
		if ( '' !== $ip && ! $this->consume( 'ip:' . $ip, $this->limits['ip'] ) ) {
			return $this->limit_error( 'ip' );
		}

		$user_id = get_current_user_id();
		if ( $user_id > 0 && ! $this->consume( 'user:' . $user_id, $this->limits['user'] ) ) {
			return $this->limit_error( 'user' );
		}

		return $result;
	}

	/**
	 * @param string $tool_name
	 * @param array  $arguments
	 */
	public function enforce_destructive( string $tool_name, array $arguments ): void {
		if ( ! $this->is_destructive( $tool_name ) ) {
			return;
		}
		$user_id = get_current_user_id();
		$key     = 'destructive:' . ( $user_id > 0 ? (string) $user_id : $this->client_ip() );
		if ( ! $this->consume( $key, $this->limits['destructive'] ) ) {
			throw new \RuntimeException( __( 'Destructive operations rate limit exceeded.', 'mcp-for-wordpress' ) );
		}
	}

	/**
	 * @param array{limit:int, window:int} $config
	 */
	private function consume( string $bucket, array $config ): bool {
		$key   = self::PREFIX . md5( $bucket );
		$now   = time();
		$state = get_transient( $key );

		if ( ! is_array( $state ) || ! isset( $state['count'], $state['expires'] ) || $state['expires'] <= $now ) {
			set_transient( $key, [ 'count' => 1, 'expires' => $now + $config['window'] ], $config['window'] );
			return true;
		}

		if ( (int) $state['count'] >= (int) $config['limit'] ) {
			return false;
		}

		$state['count'] = (int) $state['count'] + 1;
		set_transient( $key, $state, max( 1, (int) $state['expires'] - $now ) );
		return true;
	}

	private function is_destructive( string $tool_name ): bool {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return false;
		}
		$ability = wp_get_ability( $tool_name );
		if ( ! $ability ) {
			return false;
		}
		$meta = method_exists( $ability, 'get_meta' ) ? (array) $ability->get_meta() : [];
		$annotations = (array) ( $meta['annotations'] ?? [] );
		return ! empty( $annotations['destructive'] );
	}

	private function limit_error( string $bucket ): \WP_Error {
		return new \WP_Error(
			'tropk_mcp_rate_limited',
			sprintf( __( 'Rate limit exceeded (%s).', 'mcp-for-wordpress' ), $bucket ),
			[ 'status' => 429 ]
		);
	}

	private function client_ip(): string {
		$candidates = [ 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ];
		foreach ( $candidates as $key ) {
			$raw = $_SERVER[ $key ] ?? '';
			if ( ! is_string( $raw ) || '' === $raw ) {
				continue;
			}
			$first = trim( explode( ',', $raw )[0] );
			$valid = filter_var( $first, FILTER_VALIDATE_IP );
			if ( $valid ) {
				return $valid;
			}
		}
		return '';
	}
}
