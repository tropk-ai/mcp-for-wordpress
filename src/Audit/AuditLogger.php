<?php
declare(strict_types=1);

namespace Tropk\Mcp\Audit;

final class AuditLogger {

	private const SENSITIVE_KEYS = [
		'password',
		'pass',
		'pwd',
		'token',
		'access_token',
		'refresh_token',
		'secret',
		'authorization',
		'api_key',
		'apikey',
		'client_secret',
	];

	private array $timers = [];

	public function register(): void {
		add_action( 'mcp_adapter_tool_executing', [ $this, 'start_timer' ], 10, 2 );
		add_action( 'mcp_adapter_tool_executed', [ $this, 'record' ], 10, 4 );
		add_action( 'mcp_adapter_tool_error', [ $this, 'record_error' ], 10, 4 );

		if ( ! wp_next_scheduled( 'tropk_mcp_audit_cleanup' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'tropk_mcp_audit_cleanup' );
		}
		add_action( 'tropk_mcp_audit_cleanup', [ $this, 'prune_old_entries' ] );
	}

	public function start_timer( string $tool_name, array $arguments ): void {
		$this->timers[ $tool_name ] = microtime( true );
	}

	public function record( string $tool_name, array $arguments, $result, string $session_id = '' ): void {
		$this->write_entry( $tool_name, $arguments, $result, 'ok', $session_id );
	}

	public function record_error( string $tool_name, array $arguments, \Throwable $error, string $session_id = '' ): void {
		$this->write_entry(
			$tool_name,
			$arguments,
			[ 'error' => $error->getMessage() ],
			'error',
			$session_id
		);
	}

	private function write_entry( string $tool_name, array $arguments, $result, string $status, string $session_id ): void {
		global $wpdb;

		$started     = $this->timers[ $tool_name ] ?? microtime( true );
		$duration_ms = (int) round( ( microtime( true ) - $started ) * 1000 );
		unset( $this->timers[ $tool_name ] );

		$sanitized_args = self::redact( $arguments );
		$result_json    = wp_json_encode( $result );
		$result_hash    = is_string( $result_json ) ? hash( 'sha256', $result_json ) : '';
		$summary        = is_string( $result_json ) ? mb_substr( $result_json, 0, 500 ) : '';

		$wpdb->insert(
			AuditTable::table_name(),
			[
				'created_at'     => current_time( 'mysql', true ),
				'user_id'        => get_current_user_id(),
				'session_id'     => substr( $session_id, 0, 128 ),
				'tool_name'      => substr( $tool_name, 0, 190 ),
				'arguments_json' => wp_json_encode( $sanitized_args ),
				'result_summary' => $summary,
				'result_hash'    => $result_hash,
				'status'         => $status,
				'duration_ms'    => $duration_ms,
				'ip'             => self::client_ip(),
				'user_agent'     => substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 ),
			],
			[ '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
		);
	}

	public function prune_old_entries(): void {
		global $wpdb;
		$retention_days = (int) apply_filters( 'tropk_mcp_audit_retention_days', 90 );
		$cutoff         = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );
		$table          = AuditTable::table_name();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE created_at < %s",
				$cutoff
			)
		);
	}

	private static function redact( array $data ): array {
		$walker = static function ( &$value, $key ) use ( &$walker ): void {
			if ( is_array( $value ) ) {
				array_walk( $value, $walker );
				return;
			}
			if ( ! is_string( $key ) ) {
				return;
			}
			foreach ( self::SENSITIVE_KEYS as $needle ) {
				if ( false !== stripos( $key, $needle ) ) {
					$value = '[REDACTED]';
					return;
				}
			}
		};

		array_walk( $data, $walker );
		return $data;
	}

	private static function client_ip(): string {
		$candidates = [ 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ];
		foreach ( $candidates as $key ) {
			$raw = $_SERVER[ $key ] ?? '';
			if ( ! is_string( $raw ) || '' === $raw ) {
				continue;
			}
			$first = trim( explode( ',', $raw )[0] );
			$valid = filter_var( $first, FILTER_VALIDATE_IP );
			if ( $valid ) {
				return substr( $valid, 0, 45 );
			}
		}
		return '';
	}
}
