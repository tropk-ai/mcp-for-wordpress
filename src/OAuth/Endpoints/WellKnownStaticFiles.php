<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth\Endpoints;

/**
 * Cleans up legacy static OAuth metadata files and surfaces an admin notice
 * if the host blocks /.well-known/* before WordPress can answer.
 *
 * Why no static-file writing?
 * --------------------------
 * Older versions of this plugin wrote `oauth-protected-resource`,
 * `oauth-authorization-server` and `openid-configuration` directly under
 * ABSPATH/.well-known/ as a "fallback" for hosts that 404 /.well-known/
 * before PHP runs. Two bugs make that approach worse than nothing:
 *
 * 1. Extensionless static files get served WITHOUT `Content-Type:
 *    application/json` and WITHOUT CORS headers on most Apache/LiteSpeed
 *    configurations (mod_headers may not be loaded; ForceType varies by
 *    handler). ChatGPT, which probes cross-origin from chatgpt.com,
 *    silently rejects discovery responses lacking either header.
 *
 * 2. The previous "is the rewrite working?" probe did a blocking
 *    `wp_remote_get(home_url('/.well-known/openid-configuration'))` on the
 *    init hook. On hosts with restricted PHP-FPM workers (Hostinger
 *    LiteSpeed in particular), that loopback request deadlocks until the
 *    5-second WP_HTTP timeout fires — making the FIRST request after
 *    every plugin update take ~6s, which ChatGPT reports as a "Request
 *    timeout".
 *
 * So we now rely entirely on WordPress's rewrite rule. The PHP handler in
 * `MetadataEndpoints::maybe_serve_well_known()` emits the correct Content-
 * Type, CORS and Cache-Control headers. If a host blocks /.well-known/
 * before PHP, we detect it via a 12-hour-cached background probe (no
 * blocking on user-facing requests) and surface an admin notice that
 * points to the manual fix — exactly the pattern Royal MCP settled on
 * after the same series of bug reports.
 *
 * On every init we also unconditionally `@unlink` any legacy files we
 * previously wrote — idempotent (deleting a non-existent file is a no-op
 * and PHP swallows the warning), and guarantees that nobody upgrading
 * from 0.5.3-0.5.8 ends up with stale static-file responses pinned by
 * their host's CDN.
 */
final class WellKnownStaticFiles {

	public const PRM_FILE  = 'oauth-protected-resource';
	public const AS_FILE   = 'oauth-authorization-server';
	public const OIDC_FILE = 'openid-configuration';

	private const CRON_EVENT       = 'tropk_mcp_well_known_probe';
	private const TRANSIENT_RESULT = 'tropk_mcp_well_known_blocked';

	public function register(): void {
		add_action( 'init', [ $this, 'cleanup' ], 5 );
		add_action( 'admin_notices', [ $this, 'maybe_warn' ] );

		// Detect host blocking via wp_cron — runs at most once every 12h and
		// NEVER blocks an HTTP request synchronously.
		add_action( self::CRON_EVENT, [ $this, 'probe' ] );
		if ( ! wp_next_scheduled( self::CRON_EVENT ) ) {
			wp_schedule_event( time() + 60, 'twicedaily', self::CRON_EVENT );
		}
	}

	/**
	 * Idempotent. Removes any legacy static files left behind by previous
	 * plugin versions so the WP rewrite handler can take over.
	 */
	public function cleanup(): void {
		$dir = ABSPATH . '.well-known';
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( [ self::PRM_FILE, self::AS_FILE, self::OIDC_FILE ] as $name ) {
			$path = $dir . '/' . $name;
			if ( file_exists( $path ) ) {
				@unlink( $path );
			}
			$json = $path . '.json';
			if ( file_exists( $json ) ) {
				@unlink( $json );
			}
		}
		// Drop our previous .htaccess too — it referenced files that no
		// longer exist and `<Files>` directives for non-existent files are
		// silently treated as no-ops, but a leftover file is noise.
		$htaccess = $dir . '/.htaccess';
		if ( file_exists( $htaccess ) ) {
			$contents = @file_get_contents( $htaccess );
			if ( false !== $contents && false !== strpos( $contents, 'oauth-protected-resource' ) ) {
				@unlink( $htaccess );
			}
		}
	}

	/**
	 * Cron callback. Probes our own /.well-known/ URL once every 12 hours
	 * and caches the result so the admin-notice query is O(1).
	 */
	public function probe(): void {
		$response = wp_remote_get(
			home_url( '/.well-known/openid-configuration' ),
			[ 'timeout' => 8, 'redirection' => 2, 'sslverify' => false ]
		);
		if ( is_wp_error( $response ) ) {
			set_transient( self::TRANSIENT_RESULT, [
				'blocked' => true,
				'reason'  => 'wp_error: ' . $response->get_error_message(),
				'at'      => gmdate( 'c' ),
			], 12 * HOUR_IN_SECONDS );
			return;
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$ctype  = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
		$ok     = 200 === $status && false !== strpos( $ctype, 'application/json' );
		set_transient( self::TRANSIENT_RESULT, [
			'blocked' => ! $ok,
			'reason'  => $ok ? '' : sprintf( 'status=%d, content-type=%s', $status, $ctype ),
			'at'      => gmdate( 'c' ),
		], 12 * HOUR_IN_SECONDS );
	}

	public function maybe_warn(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$state = get_transient( self::TRANSIENT_RESULT );
		if ( ! is_array( $state ) || empty( $state['blocked'] ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>MCP for WP:</strong> ';
		printf(
			/* translators: %s: technical reason */
			esc_html__( 'Your host is blocking the OAuth discovery URL at %s. Claude.ai and ChatGPT need this URL to start the OAuth flow. Common causes: nginx reserves /.well-known/ for ACME SSL renewals (SiteGround, some Hostinger configs), or a CDN intercepts the path. Open the URL in a private browser tab — if it does not return a JSON response, contact your host and ask them to stop intercepting /.well-known/oauth-* paths.', 'mcp-for-wordpress' ),
			'<code>' . esc_html( home_url( '/.well-known/openid-configuration' ) ) . '</code>'
		);
		echo '</p>';
		if ( ! empty( $state['reason'] ) ) {
			echo '<p><code>' . esc_html( (string) $state['reason'] ) . '</code></p>';
		}
		echo '</div>';
	}

	public function status(): array {
		$state = get_transient( self::TRANSIENT_RESULT );
		return [
			'directory'  => ABSPATH . '.well-known',
			'serves_via' => 'wp-rewrite',
			'probe'      => is_array( $state ) ? $state : [ 'blocked' => false, 'reason' => 'not yet probed' ],
		];
	}
}
