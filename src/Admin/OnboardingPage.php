<?php
declare(strict_types=1);

namespace Tropk\Mcp\Admin;

use Tropk\Mcp\OAuth\Endpoints\AuthorizationEndpoint;
use Tropk\Mcp\OAuth\Endpoints\MetadataEndpoints;

/**
 * Top-level "Wordpress MCP" admin screen. Replaces the legacy
 * ConnectionPage with a 3-step onboarding wizard tailored to the
 * OAuth flow that Claude.ai and ChatGPT use — no Application Password
 * snippets, no Authorization header copy-paste, because both browser
 * clients do the full OAuth dance automatically (PRM discovery,
 * dynamic client registration, PKCE) once the user pastes the MCP URL.
 *
 *   step 1: pick the AI assistant (Claude / ChatGPT)
 *   step 2: client-specific OAuth instructions + copy buttons
 *   step 3: server-side health test (mcp endpoint, tools count, OAuth
 *           discovery URLs reachable)
 */
final class OnboardingPage {

	public const SLUG          = 'tropk-mcp';
	public const NONCE_ACTION  = 'tropk_mcp_admin';
	public const OPTION_STATE  = 'tropk_mcp_onboarding';
	public const TRANSIENT_REDIRECT = 'tropk_mcp_redirect_to_onboarding';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'maybe_redirect_after_activation' ] );
		add_action( 'admin_post_tropk_mcp_choose_client', [ $this, 'handle_choose_client' ] );
		add_action( 'admin_post_tropk_mcp_test_connection', [ $this, 'handle_test_connection' ] );
		add_action( 'admin_post_tropk_mcp_reset_wizard', [ $this, 'handle_reset' ] );
		// Suppress third-party admin notices (WP Rocket, etc.) on the onboarding screen only.
		add_action( 'in_admin_header', [ $this, 'maybe_suppress_admin_notices' ], 0 );
	}

	public static function on_activation(): void {
		set_transient( self::TRANSIENT_REDIRECT, 1, 60 );
		if ( ! get_option( self::OPTION_STATE ) ) {
			add_option( self::OPTION_STATE, [ 'step' => 1, 'client' => '', 'last_test' => null ], '', false );
		}
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'MCP by Tropk.ai', 'mcp-for-wordpress' ),
			__( 'MCP by Tropk.ai', 'mcp-for-wordpress' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ],
			$this->menu_icon_data_uri(),
			65
		);
	}

	/**
	 * Hide every other plugin's admin notices on our onboarding screen so the
	 * wizard stays clean (WP Rocket, Yoast, etc. love to inject banners here).
	 * Detected via the current screen's id — toplevel page hook is
	 * "toplevel_page_<slug>".
	 */
	public function maybe_suppress_admin_notices(): void {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_' . self::SLUG !== $screen->id ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
	}

	public function maybe_redirect_after_activation(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! get_transient( self::TRANSIENT_REDIRECT ) ) {
			return;
		}
		delete_transient( self::TRANSIENT_REDIRECT );
		if ( wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	public function handle_choose_client(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mcp-for-wordpress' ) );
		}
		check_admin_referer( self::NONCE_ACTION );

		$client = isset( $_POST['client'] ) ? sanitize_key( (string) $_POST['client'] ) : '';
		if ( ! in_array( $client, [ 'claude', 'chatgpt', 'cursor', 'windsurf', 'lovable' ], true ) ) {
			wp_die( esc_html__( 'Invalid client.', 'mcp-for-wordpress' ) );
		}

		$state = $this->get_state();
		$state['client'] = $client;
		$state['step']   = 2;
		update_option( self::OPTION_STATE, $state, false );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&step=2' ) );
		exit;
	}

	public function handle_test_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mcp-for-wordpress' ) );
		}
		check_admin_referer( self::NONCE_ACTION );

		$result = $this->run_health_check();
		$state  = $this->get_state();
		$state['step']      = 3;
		$state['last_test'] = $result;
		update_option( self::OPTION_STATE, $state, false );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&step=3' ) );
		exit;
	}

	public function handle_reset(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mcp-for-wordpress' ) );
		}
		check_admin_referer( self::NONCE_ACTION );

		update_option( self::OPTION_STATE, [ 'step' => 1, 'client' => '', 'last_test' => null ], false );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mcp-for-wordpress' ) );
		}

		$state          = $this->get_state();
		$requested_step = isset( $_GET['step'] ) ? (int) $_GET['step'] : (int) ( $state['step'] ?? 1 );
		$step           = max( 1, min( 3, $requested_step ) );
		$client         = (string) ( $state['client'] ?? '' );

		// Only allow advancing to step 2/3 if a client is selected.
		if ( $step >= 2 && '' === $client ) {
			$step = 1;
		}

		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — inline <style>.
		echo '<div class="wrap tropk-mcp tropk-mcp--step' . (int) $step . '">';
		echo $this->report_error_link(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — assembled from esc_*.

		switch ( $step ) {
			case 2:
				$this->render_step_two( $client );
				break;
			case 3:
				$this->render_step_three( $client, $state['last_test'] ?? null );
				break;
			case 1:
			default:
				$this->render_step_one();
		}

		echo '</div>';
	}

	private function render_step_one(): void {
		$post_url = esc_url( admin_url( 'admin-post.php' ) );
		$groups   = [
			[
				'label'   => __( 'Stable', 'mcp-for-wordpress' ),
				'class'   => 'tropk-step1__group--stable',
				'choices' => [
					[ 'slug' => 'claude',   'title' => 'Claude',   'state' => 'ok',     'badge' => '' ],
					[ 'slug' => 'lovable',  'title' => 'Lovable',  'state' => 'ok',     'badge' => '' ],
					[ 'slug' => 'cursor',   'title' => 'Cursor',   'state' => 'ok',     'badge' => '' ],
					[ 'slug' => 'windsurf', 'title' => 'Windsurf', 'state' => 'ok',     'badge' => '' ],
				],
			],
			[
				'label'   => __( 'Unstable', 'mcp-for-wordpress' ),
				'class'   => 'tropk-step1__group--unstable',
				'choices' => [
					// ChatGPT is fully disabled in the wizard (same visual as Gemini).
					// The OAuth discovery flow for ChatGPT custom connectors is still
					// being worked on — every fix we shipped for it correlated with
					// Claude breaking, so the safe move is to grey it out until we
					// have a path that doesn't disrupt the stable clients.
					[ 'slug' => 'chatgpt',  'title' => 'ChatGPT',  'state' => 'soon',   'badge' => __( 'Unstable', 'mcp-for-wordpress' ) ],
					[ 'slug' => 'gemini',   'title' => 'Gemini',   'state' => 'soon',   'badge' => __( 'Coming soon', 'mcp-for-wordpress' ) ],
				],
			],
		];
		?>
		<div class="tropk-step1">
			<h1 class="tropk-step1__title"><?php esc_html_e( 'Pick your AI assistant', 'mcp-for-wordpress' ); ?></h1>
			<?php foreach ( $groups as $group ) : ?>
				<div class="tropk-step1__group <?php echo esc_attr( $group['class'] ); ?>">
					<h2 class="tropk-step1__group-label"><?php echo esc_html( $group['label'] ); ?></h2>
					<div class="tropk-step1__row">
						<?php foreach ( $group['choices'] as $c ) :
							$slug     = $c['slug'];
							$title    = $c['title'];
							$state    = $c['state'];
							$badge    = $c['badge'];
							$disabled = 'soon' === $state; // Gemini cannot be clicked; ChatGPT can (user-opt-in).
							?>
							<form method="post" action="<?php echo $post_url; ?>" class="tropk-step1__form">
								<?php wp_nonce_field( self::NONCE_ACTION ); ?>
								<input type="hidden" name="action" value="tropk_mcp_choose_client">
								<input type="hidden" name="client" value="<?php echo esc_attr( $slug ); ?>">
								<button type="submit"
									class="tropk-step1__btn tropk-step1__btn--<?php echo esc_attr( $slug ); ?> tropk-step1__btn--<?php echo esc_attr( $state ); ?>"
									<?php echo $disabled ? 'disabled aria-disabled="true"' : ''; ?>
									aria-label="<?php echo esc_attr( sprintf( __( 'Connect to %s', 'mcp-for-wordpress' ), $title ) ); ?>">
									<?php if ( '' !== $badge ) : ?>
										<span class="tropk-step1__badge tropk-step1__badge--<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $badge ); ?></span>
									<?php endif; ?>
									<img class="tropk-step1__logo" src="<?php echo esc_url( TROPK_MCP_URL . 'assets/logos/' . $slug . '.svg?ver=' . TROPK_MCP_VERSION ); ?>" alt="<?php echo esc_attr( $title ); ?>">
									<span class="tropk-step1__name"><?php echo esc_html( $title ); ?></span>
								</button>
							</form>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function render_step_two( string $client ): void {
		$endpoint  = rest_url( 'tropk-mcp/v1/mcp' );
		$site_name = (string) get_bloginfo( 'name' );
		$slug      = sanitize_title( '' !== $site_name ? $site_name : 'wordpress-mcp' );
		if ( '' === $slug ) {
			$slug = 'wordpress-mcp';
		}
		$nonce_field = wp_nonce_field( self::NONCE_ACTION, '_wpnonce', true, false );

		// Pre-built one-click install URLs. Where the assistant publishes a
		// stable install-link scheme we use it; otherwise we fall back to
		// "open settings + copy URL". Claude.ai documents its scheme at
		// https://claude.com/docs/connectors/building/directory-vs-custom
		// and Cursor documents its at https://cursor.com/docs/context/mcp/install-links.
		$one_click_url = '';
		if ( 'claude' === $client ) {
			$one_click_url = add_query_arg(
				[
					'modal'        => 'add-custom-connector',
					'connectorName' => rawurlencode( $site_name ?: 'Wordpress MCP' ),
					'connectorUrl'  => rawurlencode( $endpoint ),
				],
				'https://claude.ai/customize/connectors'
			);
		} elseif ( 'cursor' === $client ) {
			// Cursor deep-link install per https://cursor.com/docs/context/mcp/install-links
			// Config must declare `type: "http"` for remote servers. Base64-encoded raw
			// (NOT url-encoded again) per docs — the URL params get rawurlencoded as a whole.
			$config = base64_encode( (string) wp_json_encode( [
				'type' => 'http',
				'url'  => $endpoint,
			] ) );
			$one_click_url = sprintf(
				'cursor://anysphere.cursor-deeplink/mcp/install?name=%s&config=%s',
				rawurlencode( $site_name ?: 'wordpress-mcp' ),
				$config
			);
		} elseif ( 'windsurf' === $client ) {
			$one_click_url = ''; // Windsurf has no deep link for custom remote URLs — manual JSON paste only.
		}

		switch ( $client ) {
			case 'claude':
				$brand_name    = 'Claude.ai';
				$assistant_url = 'https://claude.ai/settings/connectors';
				$instructions  = [
					__( 'Click <strong>One-click install ↗</strong> below. Claude.ai opens with the connector name + URL pre-filled.', 'mcp-for-wordpress' ),
					__( 'Review the values, click <strong>Add</strong>, then <strong>Link</strong>. A WordPress login window will open — sign in with the account you want the AI to act as.', 'mcp-for-wordpress' ),
					__( 'Click <strong>Allow</strong> on the consent screen. The tools appear in Claude.', 'mcp-for-wordpress' ),
				];
				$accent_class  = 'tropk-step--claude';
				break;
			case 'chatgpt':
				$brand_name    = 'ChatGPT';
				$assistant_url = 'https://chatgpt.com/#settings/Connectors';
				$instructions  = [
					__( 'Open <strong>ChatGPT</strong> → <strong>Settings → Apps & Connectors → Advanced settings</strong> and enable developer mode.', 'mcp-for-wordpress' ),
					__( 'Go to <strong>Settings → Apps & Connectors → Create</strong>. Give it any name.', 'mcp-for-wordpress' ),
					__( 'Paste the MCP URL below into the <strong>MCP Server URL</strong> field. ChatGPT does not (yet) support 1-click install — manual paste is the only path.', 'mcp-for-wordpress' ),
					__( 'Click <strong>Create</strong>, sign into WordPress when prompted, click <strong>Allow</strong>.', 'mcp-for-wordpress' ),
				];
				$accent_class  = 'tropk-step--chatgpt';
				break;
			case 'cursor':
				$brand_name    = 'Cursor';
				$assistant_url = 'https://cursor.com/settings';
				$instructions  = [
					__( 'Click <strong>One-click install ↗</strong>. Cursor opens its MCP-install dialog with the URL pre-filled.', 'mcp-for-wordpress' ),
					__( 'Click <strong>Add server</strong>. On first use, Cursor opens the WordPress consent screen in a browser — sign in, then click <strong>Allow</strong>.', 'mcp-for-wordpress' ),
				];
				$accent_class  = 'tropk-step--cursor';
				break;
			case 'windsurf':
				$brand_name    = 'Windsurf';
				$assistant_url = ''; // Windsurf has no web settings page worth deep-linking to — the config lives in a local file.
				$instructions  = [
					__( 'Open <strong>Windsurf</strong> → <strong>Settings → Cascade → MCP servers → Add custom server +</strong>. Windsurf opens <code>~/.codeium/windsurf/mcp_config.json</code> in its editor.', 'mcp-for-wordpress' ),
					__( 'Paste the JSON below (or merge it into the existing <code>mcpServers</code> block, keeping any other servers you already have).', 'mcp-for-wordpress' ),
					__( 'Save the file. <strong>Then go back to the Cascade panel and click the Refresh (🔄) button</strong> — without that, Windsurf does NOT reload the config.', 'mcp-for-wordpress' ),
					sprintf(
						/* translators: %s is the URL of the WP-Admin Application Passwords section */
						__( '⚠️ Windsurf does not yet support OAuth for remote MCP — only a static Bearer/PAT. To use this plugin, generate an <a href="%s">Application Password</a> and add an <code>Authorization: Basic …</code> header in the JSON (example below).', 'mcp-for-wordpress' ),
						esc_url( admin_url( 'profile.php#application-passwords-section' ) )
					),
				];
				$accent_class  = 'tropk-step--windsurf';
				break;
			case 'lovable':
				$brand_name    = 'Lovable';
				$assistant_url = 'https://lovable.dev/connect';
				$instructions  = [
					__( 'Open <strong>Lovable</strong> → <strong>Connectors → New MCP server</strong>.', 'mcp-for-wordpress' ),
					__( 'Name the connector and paste the URL below. Leave authentication on <strong>OAuth</strong> (default).', 'mcp-for-wordpress' ),
					__( 'Sign into WordPress when prompted; click <strong>Allow</strong>.', 'mcp-for-wordpress' ),
				];
				$accent_class  = 'tropk-step--lovable';
				break;
			default:
				$brand_name    = 'your assistant';
				$assistant_url = '';
				$instructions  = [
					__( 'Open the MCP / custom-connector settings of your AI assistant.', 'mcp-for-wordpress' ),
					__( 'Paste the URL below.', 'mcp-for-wordpress' ),
					__( 'Sign into WordPress when prompted; click <strong>Allow</strong>.', 'mcp-for-wordpress' ),
				];
				$accent_class  = '';
		}

		?>
		<div class="tropk-step tropk-step--two">
		<div class="tropk-card <?php echo esc_attr( $accent_class ); ?>">
			<h2><?php
				printf(
					/* translators: %s: brand name */
					esc_html__( 'Step 2 · Connect %s', 'mcp-for-wordpress' ),
					esc_html( $brand_name )
				);
			?></h2>

			<p class="tropk-lede">
				<?php
				printf(
					/* translators: %s: brand name */
					esc_html__( '%s uses OAuth 2.1 with automatic dynamic client registration. You only need the URL below — no headers, no Application Passwords, no API keys.', 'mcp-for-wordpress' ),
					'<strong>' . esc_html( $brand_name ) . '</strong>'
				);
				?>
			</p>

			<?php if ( '' !== $one_click_url ) :
				// esc_url() strips non-http(s) protocols (e.g. cursor://), so we esc_url for http(s)
				// and esc_attr() for app protocols. The browser opens the local app via the handler.
				$is_app_protocol = 0 !== strpos( $one_click_url, 'http' );
				$safe_href       = $is_app_protocol ? esc_attr( $one_click_url ) : esc_url( $one_click_url );
				$link_target     = $is_app_protocol ? '_self' : '_blank';
				?>
				<a class="tropk-oneclick" href="<?php echo $safe_href; // phpcs:ignore WordPress.Security.EscapeOutput ?>" target="<?php echo esc_attr( $link_target ); ?>" rel="noopener noreferrer">
					<span class="tropk-oneclick__icon">⚡</span>
					<span class="tropk-oneclick__label">
						<?php
						printf(
							/* translators: %s: assistant name */
							esc_html__( 'One-click install in %s', 'mcp-for-wordpress' ),
							esc_html( $brand_name )
						);
						?>
					</span>
					<span class="tropk-oneclick__hint"><?php esc_html_e( 'opens the AI app with the connector pre-filled', 'mcp-for-wordpress' ); ?></span>
				</a>
				<p class="tropk-divider"><span><?php esc_html_e( 'or paste manually', 'mcp-for-wordpress' ); ?></span></p>
			<?php endif; ?>

			<?php if ( 'windsurf' === $client ) :
				// Windsurf currently only supports Bearer/PAT auth on remote MCP
				// servers — OAuth is not implemented (as of Windsurf 1.x docs).
				// The user has to generate a WordPress Application Password and
				// paste it as a Basic auth header. We emit BOTH JSON variants —
				// one OAuth-only (for when Windsurf gains OAuth support) and one
				// with Application Password Basic auth so it works today.
				$slug = sanitize_title( get_bloginfo( 'name' ) ?: 'wordpress-mcp' );
				$windsurf_config = [
					'mcpServers' => [
						$slug => [
							'serverUrl' => $endpoint,
							'headers'   => [
								'Authorization' => 'Basic <BASE64_OF_USERNAME:APPLICATION_PASSWORD>',
							],
						],
					],
				];
				$windsurf_json = (string) wp_json_encode(
					$windsurf_config,
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
				);
				?>
				<div class="tropk-url-row">
					<label class="tropk-url-label" for="tropk-windsurf-json"><?php esc_html_e( 'Snippet for Windsurf mcp_config.json', 'mcp-for-wordpress' ); ?></label>
					<div class="tropk-copy-block">
						<pre id="tropk-windsurf-json" class="tropk-codeblock"><code><?php echo esc_html( $windsurf_json ); ?></code></pre>
						<button type="button" class="tropk-copy-btn tropk-copy-btn--block" data-copy-text="<?php echo esc_attr( $windsurf_json ); ?>">
							<?php esc_html_e( 'Copy', 'mcp-for-wordpress' ); ?>
						</button>
					</div>
					<p class="tropk-note">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s is the URL of the WP-Admin Profile page */
								__( '<strong>How to generate the Application Password:</strong> WP-Admin → <a href="%1$s" target="_blank">Users → Profile</a> → scroll to "Application Passwords" → any name (e.g. "Windsurf") → <strong>Add new application password</strong>. Copy the generated password, combine it with your username as <code>username:password</code>, base64-encode it (use <a href="https://www.base64encode.org/" target="_blank">base64encode.org</a> or run <code>echo -n \'user:password\' | base64</code>) and replace <code>&lt;BASE64_OF_USERNAME:APPLICATION_PASSWORD&gt;</code> in the JSON.', 'mcp-for-wordpress' ),
								esc_url( admin_url( 'profile.php#application-passwords-section' ) )
							),
							[ 'code' => [], 'strong' => [], 'a' => [ 'href' => [], 'target' => [] ] ]
						);
						?>
					</p>
					<p class="tropk-note tropk-note--warning">
						<?php esc_html_e( 'After saving the file, click the Refresh (🔄) button in the Cascade panel — without it Windsurf does not reload the config.', 'mcp-for-wordpress' ); ?>
					</p>
				</div>
			<?php else : ?>
				<div class="tropk-url-row">
					<label class="tropk-url-label" for="tropk-mcp-url"><?php esc_html_e( 'MCP Server URL', 'mcp-for-wordpress' ); ?></label>
					<div class="tropk-copy-row">
						<input id="tropk-mcp-url" type="text" readonly value="<?php echo esc_attr( $endpoint ); ?>">
						<button type="button" class="tropk-copy-btn" data-copy-target="#tropk-mcp-url">
							<?php esc_html_e( 'Copy', 'mcp-for-wordpress' ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<ol class="tropk-steps">
				<?php foreach ( $instructions as $i => $line ) : ?>
					<li>
						<?php
						echo wp_kses(
							$line,
							[
								'strong' => [],
								'code'   => [],
								'a'      => [ 'href' => [], 'target' => [], 'rel' => [] ],
							]
						);
						?>
					</li>
				<?php endforeach; ?>
			</ol>

			<div class="tropk-actions">
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&step=1' ) ); ?>">
					<?php esc_html_e( '← Back', 'mcp-for-wordpress' ); ?>
				</a>
				<?php if ( '' !== $assistant_url ) : ?>
					<a class="button button-secondary" href="<?php echo esc_url( $assistant_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php
						printf(
							/* translators: %s: assistant brand name */
							esc_html__( 'Open %s settings ↗', 'mcp-for-wordpress' ),
							esc_html( $brand_name )
						);
						?>
					</a>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
					<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_nonce_field already escaped. ?>
					<input type="hidden" name="action" value="tropk_mcp_test_connection">
					<button type="submit" class="button button-primary button-hero">
						<?php esc_html_e( "I've connected — test the connection →", 'mcp-for-wordpress' ); ?>
					</button>
				</form>
			</div>
		</div>
		</div>

		<?php echo $this->copy_script(); // phpcs:ignore ?>
		<?php
	}

	/**
	 * @param string                $client
	 * @param array<string,mixed>|null $last_test
	 */
	private function render_step_three( string $client, $last_test ): void {
		$retest_form_open = sprintf(
			'<form method="post" action="%s" style="margin:0;">%s<input type="hidden" name="action" value="tropk_mcp_test_connection">',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( self::NONCE_ACTION, '_wpnonce', true, false )
		);

		$result = is_array( $last_test ) ? $last_test : $this->run_health_check();
		$is_ok  = ! empty( $result['ok'] );

		?>
		<div class="tropk-step tropk-step--three">
		<div class="tropk-card <?php echo $is_ok ? 'tropk-step--ok' : 'tropk-step--fail'; ?>">
			<h2>
				<?php if ( $is_ok ) : ?>
					<span class="tropk-badge tropk-badge--ok">✓</span>
					<?php esc_html_e( 'Step 3 · Connected', 'mcp-for-wordpress' ); ?>
				<?php else : ?>
					<span class="tropk-badge tropk-badge--fail">!</span>
					<?php esc_html_e( 'Step 3 · Not connected yet', 'mcp-for-wordpress' ); ?>
				<?php endif; ?>
			</h2>

			<?php if ( $is_ok ) : ?>
				<p class="tropk-lede">
					<?php
					printf(
						/* translators: %d: tool count */
						esc_html__( 'The MCP server is live and currently exposes %d tools. Go back to your AI assistant — it should list every tool under this connector now.', 'mcp-for-wordpress' ),
						(int) ( $result['tools'] ?? 0 )
					);
					?>
				</p>
				<table class="tropk-checks">
					<?php foreach ( $result['checks'] as $check ) : ?>
						<tr class="<?php echo esc_attr( $check['ok'] ? 'ok' : 'warn' ); ?>">
							<td class="tropk-checks__mark"><?php echo $check['ok'] ? '✓' : '!'; ?></td>
							<td class="tropk-checks__label"><?php echo esc_html( $check['label'] ); ?></td>
							<td class="tropk-checks__detail"><?php echo esc_html( $check['detail'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php else : ?>
				<p class="tropk-lede">
					<?php esc_html_e( "We couldn't verify the connection. Check the items below — if everything is green and your AI client still doesn't see the connector, click \"Test again\".", 'mcp-for-wordpress' ); ?>
				</p>
				<table class="tropk-checks">
					<?php foreach ( $result['checks'] as $check ) : ?>
						<tr class="<?php echo esc_attr( $check['ok'] ? 'ok' : 'fail' ); ?>">
							<td class="tropk-checks__mark"><?php echo $check['ok'] ? '✓' : '✕'; ?></td>
							<td class="tropk-checks__label"><?php echo esc_html( $check['label'] ); ?></td>
							<td class="tropk-checks__detail"><?php echo esc_html( $check['detail'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endif; ?>

			<details class="tropk-tip">
				<summary><?php esc_html_e( 'Show the raw connection details', 'mcp-for-wordpress' ); ?></summary>
				<dl class="tropk-kv">
					<dt><?php esc_html_e( 'MCP endpoint', 'mcp-for-wordpress' ); ?></dt>
					<dd><code><?php echo esc_html( rest_url( 'tropk-mcp/v1/mcp' ) ); ?></code></dd>

					<dt><?php esc_html_e( 'Protected Resource Metadata', 'mcp-for-wordpress' ); ?></dt>
					<dd><code><?php echo esc_html( MetadataEndpoints::rest_prm_url() ); ?></code></dd>

					<dt><?php esc_html_e( 'Authorization Server Metadata', 'mcp-for-wordpress' ); ?></dt>
					<dd><code><?php echo esc_html( MetadataEndpoints::rest_as_url() ); ?></code></dd>

					<dt><?php esc_html_e( 'Authorize URL', 'mcp-for-wordpress' ); ?></dt>
					<dd><code><?php echo esc_html( AuthorizationEndpoint::url() ); ?></code></dd>

					<dt><?php esc_html_e( 'Token endpoint', 'mcp-for-wordpress' ); ?></dt>
					<dd><code><?php echo esc_html( rest_url( 'tropk-mcp/v1/oauth/token' ) ); ?></code></dd>
				</dl>
			</details>

			<div class="tropk-actions">
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&step=2' ) ); ?>">
					<?php esc_html_e( '← Back to instructions', 'mcp-for-wordpress' ); ?>
				</a>
				<?php echo $retest_form_open; // phpcs:ignore ?>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( '⟳ Test again', 'mcp-for-wordpress' ); ?>
					</button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>
					<input type="hidden" name="action" value="tropk_mcp_reset_wizard">
					<button type="submit" class="button button-secondary">
						<?php esc_html_e( 'Restart wizard', 'mcp-for-wordpress' ); ?>
					</button>
				</form>
			</div>
		</div>
		</div>
		<?php
	}

	/**
	 * @return array<string,mixed>
	 */
	private function get_state(): array {
		$state = get_option( self::OPTION_STATE, [] );
		if ( ! is_array( $state ) ) {
			$state = [];
		}
		return wp_parse_args( $state, [ 'step' => 1, 'client' => '', 'last_test' => null ] );
	}

	/**
	 * Server-side health check: route presence, abilities + tools count,
	 * OAuth discovery URLs reachable, static .well-known files present.
	 *
	 * @return array<string,mixed>
	 */
	private function run_health_check(): array {
		$checks = [];

		$rest_routes  = rest_get_server()->get_routes();
		$route_ok     = array_key_exists( '/tropk-mcp/v1/mcp', $rest_routes );
		$checks[]     = [
			'ok'     => $route_ok,
			'label'  => __( 'MCP endpoint registered', 'mcp-for-wordpress' ),
			'detail' => $route_ok ? rest_url( 'tropk-mcp/v1/mcp' ) : __( 'Plugin failed to register the REST route.', 'mcp-for-wordpress' ),
		];

		$tools = 0;
		if ( class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) ) {
			$adapter = \WP\MCP\Core\McpAdapter::instance();
			if ( method_exists( $adapter, 'init' ) ) {
				$adapter->init();
			}
			if ( method_exists( $adapter, 'get_server' ) ) {
				$server = $adapter->get_server( 'tropk-mcp-server' );
				if ( $server && method_exists( $server, 'get_tools' ) ) {
					$tools = (int) count( (array) $server->get_tools() );
				}
			}
		}
		$tools_ok = $tools > 0;
		$checks[] = [
			'ok'     => $tools_ok,
			'label'  => __( 'Tools registered', 'mcp-for-wordpress' ),
			'detail' => $tools_ok ? sprintf( _n( '%d tool', '%d tools', $tools, 'mcp-for-wordpress' ), $tools ) : __( 'No tools registered — Abilities API may have failed to initialise.', 'mcp-for-wordpress' ),
		];

		$prm_url = MetadataEndpoints::rest_prm_url();
		$prm     = wp_remote_get( $prm_url, [ 'timeout' => 5 ] );
		$prm_status = is_wp_error( $prm ) ? 0 : (int) wp_remote_retrieve_response_code( $prm );
		$prm_ok  = 200 === $prm_status;
		$checks[] = [
			'ok'     => $prm_ok,
			'label'  => __( 'OAuth Protected Resource Metadata', 'mcp-for-wordpress' ),
			'detail' => $prm_ok ? $prm_url : sprintf( __( 'HTTP %d when fetching %s', 'mcp-for-wordpress' ), $prm_status, $prm_url ),
		];

		$as_url = MetadataEndpoints::rest_as_url();
		$as     = wp_remote_get( $as_url, [ 'timeout' => 5 ] );
		$as_status = is_wp_error( $as ) ? 0 : (int) wp_remote_retrieve_response_code( $as );
		$as_ok  = 200 === $as_status;
		$checks[] = [
			'ok'     => $as_ok,
			'label'  => __( 'OAuth Authorization Server Metadata', 'mcp-for-wordpress' ),
			'detail' => $as_ok ? $as_url : sprintf( __( 'HTTP %d when fetching %s', 'mcp-for-wordpress' ), $as_status, $as_url ),
		];

		$wk_prm_url = home_url( '/.well-known/oauth-protected-resource' );
		$wk_prm     = wp_remote_get( $wk_prm_url, [ 'timeout' => 5 ] );
		$wk_status  = is_wp_error( $wk_prm ) ? 0 : (int) wp_remote_retrieve_response_code( $wk_prm );
		$wk_ok      = 200 === $wk_status;
		$checks[]   = [
			'ok'     => $wk_ok,
			'label'  => __( 'Standard /.well-known/ discovery path', 'mcp-for-wordpress' ),
			'detail' => $wk_ok
				? $wk_prm_url
				: sprintf( __( 'HTTP %d when fetching %s — most hosts allow .well-known, but the REST-API discovery URLs above still work as a fallback.', 'mcp-for-wordpress' ), $wk_status, $wk_prm_url ),
		];

		$probe_url  = rest_url( 'tropk-mcp/v1/mcp' );
		$probe      = wp_remote_post(
			$probe_url,
			[
				'timeout' => 5,
				'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream' ],
				'body'    => wp_json_encode( [ 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [ 'protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass(), 'clientInfo' => [ 'name' => 'tropk-wizard', 'version' => '1' ] ] ] ),
			]
		);
		$probe_status = is_wp_error( $probe ) ? 0 : (int) wp_remote_retrieve_response_code( $probe );
		$www_auth     = is_wp_error( $probe ) ? '' : (string) wp_remote_retrieve_header( $probe, 'www-authenticate' );
		$challenge_ok = 401 === $probe_status && false !== stripos( $www_auth, 'bearer' ) && false !== stripos( $www_auth, 'resource_metadata' );
		$checks[]     = [
			'ok'     => $challenge_ok,
			'label'  => __( 'Unauthenticated request returns OAuth challenge', 'mcp-for-wordpress' ),
			'detail' => $challenge_ok
				? __( '401 + WWW-Authenticate header points at the PRM URL. AI clients can discover the OAuth flow.', 'mcp-for-wordpress' )
				: sprintf( __( 'Expected 401 + Bearer challenge; got HTTP %d. Some host firewall (Cloudflare, Wordfence) may be blocking the endpoint.', 'mcp-for-wordpress' ), $probe_status ),
		];

		$ok = $route_ok && $tools_ok && $prm_ok && $as_ok && $challenge_ok;

		return [
			'ok'     => $ok,
			'tools'  => $tools,
			'checks' => $checks,
			'at'     => gmdate( 'c' ),
		];
	}

	private function header_block( int $current_step ): string {
		ob_start();
		?>
		<header class="tropk-hero">
			<div class="tropk-hero__brand">
				<?php echo $this->brand_logo_svg(); // phpcs:ignore ?>
				<div>
					<h1><?php esc_html_e( 'Wordpress MCP', 'mcp-for-wordpress' ); ?></h1>
					<p class="tropk-hero__sub">
						<?php
						printf(
							/* translators: %s: brand */
							esc_html__( 'by %s — connect this site to Claude, ChatGPT, Cursor and any MCP-compatible AI assistant.', 'mcp-for-wordpress' ),
							'<strong>Tropk.ai</strong>'
						);
						?>
					</p>
				</div>
			</div>
			<ol class="tropk-progress">
				<?php foreach ( [ 1 => __( 'Choose assistant', 'mcp-for-wordpress' ), 2 => __( 'Connect', 'mcp-for-wordpress' ), 3 => __( 'Test', 'mcp-for-wordpress' ) ] as $n => $label ) : ?>
					<li class="<?php echo $n < $current_step ? 'done' : ( $n === $current_step ? 'current' : 'pending' ); ?>">
						<span class="tropk-progress__dot"><?php echo (int) $n; ?></span>
						<span class="tropk-progress__label"><?php echo esc_html( $label ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		</header>
		<?php
		return (string) ob_get_clean();
	}

	private function styles(): string {
		return <<<'CSS'
<style>
.wrap.tropk-mcp { max-width: 920px; }
.tropk-mcp h1, .tropk-mcp h2 { font-weight: 600; }

/* All steps render full-bleed inside .wrap so the inner .tropk-step wrapper
   can take over and centre its contents on both axes. */
.wrap.tropk-mcp.tropk-mcp--step1,
.wrap.tropk-mcp.tropk-mcp--step2,
.wrap.tropk-mcp.tropk-mcp--step3 { max-width: none; margin: 0; padding: 0; }

/* Generic centred wrapper used by every step (1, 2 and 3). */
.tropk-step {
	min-height: calc(100vh - 32px);
	display: flex; flex-direction: column;
	align-items: center; justify-content: center;
	gap: 24px; padding: 32px 24px;
	box-sizing: border-box;
}
.tropk-step .tropk-card {
	width: 100%; max-width: 760px; margin: 0;
}

/* Floating "Report an error" link — bottom-right corner of every step. */
.tropk-report {
	position: fixed; bottom: 16px; right: 20px; z-index: 9999;
	display: inline-flex; align-items: center; gap: 6px;
	padding: 6px 12px; border-radius: 999px;
	background: rgba(255,255,255,.9); color: #50575e;
	text-decoration: none; font-size: 12px; line-height: 1;
	border: 1px solid #dcdcde;
	box-shadow: 0 2px 8px rgba(0,0,0,.06);
	transition: color .15s ease, border-color .15s ease, box-shadow .15s ease;
	backdrop-filter: blur(4px);
}
.tropk-report:hover, .tropk-report:focus-visible {
	color: #d63638; border-color: #d63638; box-shadow: 0 4px 14px rgba(214,54,56,.18);
}
.tropk-report__icon { display: inline-flex; }
.tropk-report__label { font-weight: 500; }
.tropk-report__email { font-weight: 600; }
@media (max-width: 640px) {
	.tropk-report__label { display: none; }
}

/* Step 1 — minimalist: only title + 6 logo buttons in a single row, centered both axes. */
.tropk-step1 {
	min-height: calc(100vh - 32px);
	display: flex; flex-direction: column;
	align-items: center; justify-content: center;
	gap: 48px; padding: 24px;
	box-sizing: border-box;
}
.tropk-step1__title {
	font-size: clamp(22px, 3.2vw, 36px);
	font-weight: 600; margin: 0; text-align: center; color: #1d2327;
}
.tropk-step1__group {
	display: flex; flex-direction: column; align-items: center; gap: 14px;
	width: 100%; max-width: 1200px;
}
.tropk-step1__group-label {
	font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
	color: #8c8f94; margin: 0;
}
.tropk-step1__group--unstable .tropk-step1__group-label { color: #b54708; }
.tropk-step1__row {
	display: flex; flex-wrap: nowrap; justify-content: center; align-items: stretch;
	gap: 14px; width: 100%;
}
.tropk-step1__form { margin: 0; flex: 0 0 168px; min-width: 0; }
.tropk-step1__btn {
	width: 100%; aspect-ratio: 1 / 1;
	display: flex; flex-direction: column; align-items: center; justify-content: center;
	gap: 14px; padding: 16px;
	border: 2px solid #dcdcde; border-radius: 16px;
	background: #fff; cursor: pointer; font: inherit; color: #1d2327;
	transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
	position: relative; box-sizing: border-box;
}
.tropk-step1__btn:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
.tropk-step1__btn:focus-visible { outline: 3px solid #2271b1; outline-offset: 2px; }
.tropk-step1__btn--claude:hover   { border-color: #c87b3b; }
.tropk-step1__btn--chatgpt:hover  { border-color: #0d8a6c; }
.tropk-step1__btn--cursor:hover   { border-color: #1a1a1a; }
.tropk-step1__btn--windsurf:hover { border-color: #00c2a8; }
.tropk-step1__btn--lovable:hover  { border-color: #ff5470; }
.tropk-step1__btn--gemini:hover   { border-color: #4285f4; }
.tropk-step1__logo { width: clamp(40px, 4.5vw, 64px); height: clamp(40px, 4.5vw, 64px); object-fit: contain; }
.tropk-step1__name { font-size: clamp(13px, 1.1vw, 17px); font-weight: 600; }

/* Unstable group — softer look so user knows it's a known-buggy path. */
.tropk-step1__btn--unstable { background: #fffbeb; border-color: #f6c343; opacity: .85; }
.tropk-step1__btn--unstable:hover { opacity: 1; border-color: #b54708; transform: translateY(-3px); }

/* Coming-soon — fully disabled. */
.tropk-step1__btn--soon { cursor: not-allowed; opacity: 0.5; background: #f6f7f7; }
.tropk-step1__btn--soon:hover { transform: none; box-shadow: none; border-color: #dcdcde; }

/* Status badge in the top-right corner of the button. */
.tropk-step1__badge {
	position: absolute; top: 8px; right: 8px;
	font-size: 9px; line-height: 1; padding: 4px 7px; border-radius: 999px;
	font-weight: 700; letter-spacing: .03em; text-transform: uppercase;
	white-space: nowrap;
}
.tropk-step1__badge--unstable { background: #b54708; color: #fff; }
.tropk-step1__badge--soon     { background: #50575e; color: #fff; }

@media (max-width: 720px) {
	.tropk-step1__row { flex-wrap: wrap; }
	.tropk-step1__form { flex: 0 1 calc(50% - 7px); max-width: none; }
}

.tropk-hero {
	background: linear-gradient(135deg, #1d2327 0%, #2c3338 100%);
	color: #fff;
	border-radius: 12px;
	padding: 28px 32px;
	margin: 16px 0 24px;
}
.tropk-hero__brand { display: flex; align-items: center; gap: 18px; }
.tropk-hero h1 { color: #fff; font-size: 28px; margin: 0; }
.tropk-hero__sub { color: #c3c4c7; font-size: 14px; margin: 4px 0 0; }
.tropk-progress {
	display: flex; gap: 12px; margin: 22px 0 0; padding: 0; list-style: none;
	font-size: 12px; color: #c3c4c7;
}
.tropk-progress li { display: flex; align-items: center; gap: 8px; }
.tropk-progress li:not(:last-child)::after {
	content: ''; display: inline-block; width: 28px; height: 1px; background: #50575e; margin-left: 8px;
}
.tropk-progress__dot {
	width: 24px; height: 24px; border-radius: 50%; display: inline-flex;
	align-items: center; justify-content: center; font-weight: 600;
	border: 1px solid #50575e; background: #2c3338;
}
.tropk-progress .current .tropk-progress__dot { background: #2271b1; border-color: #2271b1; color: #fff; }
.tropk-progress .done .tropk-progress__dot { background: #00a32a; border-color: #00a32a; color: #fff; }
.tropk-progress .current .tropk-progress__label,
.tropk-progress .done .tropk-progress__label { color: #fff; }

.tropk-card {
	background: #fff; border: 1px solid #c3c4c7; border-radius: 12px;
	padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.tropk-card h2 { margin-top: 0; font-size: 22px; }
.tropk-lede { font-size: 15px; color: #50575e; max-width: 720px; line-height: 1.6; }

.tropk-choice-grid {
	display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px;
	margin: 28px 0 8px;
}
.tropk-choice { position: relative; }
.tropk-choice__badge {
	position: absolute; top: 10px; right: 10px;
	background: #00a32a; color: #fff; font-size: 11px;
	padding: 3px 7px; border-radius: 10px; font-weight: 600;
}
.tropk-choice--cursor:hover    { border-color: #1a1a1a; }
.tropk-choice--windsurf:hover  { border-color: #3a8a6c; }
.tropk-choice--lovable:hover   { border-color: #ff5470; }
.tropk-choice--cursor:hover .tropk-choice__cta   { color: #1a1a1a; }
.tropk-choice--windsurf:hover .tropk-choice__cta { color: #3a8a6c; }
.tropk-choice--lovable:hover .tropk-choice__cta  { color: #ff5470; }

.tropk-oneclick {
	display: flex; align-items: center; gap: 14px;
	margin: 24px 0;
	padding: 18px 22px; border-radius: 10px;
	background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
	color: #fff; text-decoration: none;
	box-shadow: 0 3px 12px rgba(34,113,177,.25);
	transition: transform .15s ease, box-shadow .15s ease;
}
.tropk-oneclick:hover {
	color: #fff; transform: translateY(-1px);
	box-shadow: 0 6px 18px rgba(34,113,177,.35);
}
.tropk-oneclick__icon { font-size: 24px; }
.tropk-oneclick__label { font-weight: 600; font-size: 16px; }
.tropk-oneclick__hint { font-size: 12px; opacity: .85; margin-left: auto; }

.tropk-divider {
	display: flex; align-items: center; gap: 12px;
	margin: 18px 0;
	color: #8c8f94; font-size: 12px; text-transform: uppercase; letter-spacing: .05em;
}
.tropk-divider::before, .tropk-divider::after {
	content: ''; flex: 1; border-top: 1px solid #dcdcde;
}
.tropk-choice {
	width: 100%; display: flex; flex-direction: column; align-items: center;
	gap: 12px; padding: 32px 24px; border: 2px solid #dcdcde; border-radius: 12px;
	background: #fff; cursor: pointer; transition: all .15s ease;
	font: inherit; color: #1d2327;
}
.tropk-choice:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.08); }
.tropk-choice--claude:hover { border-color: #c87b3b; }
.tropk-choice--chatgpt:hover { border-color: #10a37f; }
.tropk-choice__logo { width: 56px; height: 56px; display: block; }
.tropk-choice__title { font-size: 22px; font-weight: 600; }
.tropk-choice__sub { font-size: 12px; color: #646970; }
.tropk-choice__cta { margin-top: 8px; font-weight: 600; font-size: 14px; color: #2271b1; }
.tropk-choice--claude:hover .tropk-choice__cta { color: #c87b3b; }
.tropk-choice--chatgpt:hover .tropk-choice__cta { color: #10a37f; }

.tropk-url-row { margin: 24px 0; }
.tropk-url-label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #1d2327; }
.tropk-copy-row { display: flex; gap: 6px; }
.tropk-copy-row input {
	flex: 1; font-family: SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px;
	padding: 10px 12px; border: 1px solid #c3c4c7; border-radius: 4px; background: #f6f7f7;
}
.tropk-copy-btn {
	padding: 10px 18px; border: 1px solid #2271b1; border-radius: 4px;
	background: #2271b1; color: #fff; cursor: pointer; font-weight: 600;
	min-width: 80px;
}
.tropk-copy-btn.copied { background: #00a32a; border-color: #00a32a; }

.tropk-copy-block { position: relative; }
.tropk-codeblock {
	margin: 0; padding: 14px 16px; padding-right: 92px;
	background: #1d2327; color: #f6f7f7;
	border-radius: 6px; border: 1px solid #2c3338;
	font-family: SFMono-Regular, Menlo, Consolas, monospace; font-size: 12.5px;
	line-height: 1.55; overflow-x: auto; white-space: pre;
}
.tropk-codeblock code { background: transparent; color: inherit; padding: 0; border: 0; font: inherit; }
.tropk-copy-btn--block {
	position: absolute; top: 10px; right: 10px;
	padding: 6px 12px; font-size: 12px; min-width: 0;
}

.tropk-steps { padding-left: 20px; margin: 24px 0; }
.tropk-steps li { margin: 10px 0; line-height: 1.6; }
.tropk-steps code { background: #f0f0f1; padding: 1px 6px; border-radius: 3px; }

.tropk-tip { margin: 24px 0; border: 1px solid #dcdcde; border-radius: 6px; padding: 12px 16px; background: #f6f7f7; }
.tropk-tip summary { cursor: pointer; font-weight: 600; color: #50575e; }
.tropk-tip ol, .tropk-tip dl { margin: 12px 0 4px; }
.tropk-tip dt { font-weight: 600; margin-top: 8px; }
.tropk-tip dd { margin: 2px 0 0 0; }
.tropk-tip code { background: #fff; padding: 1px 6px; border: 1px solid #dcdcde; border-radius: 3px; }

.tropk-actions {
	display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
	margin-top: 28px; padding-top: 22px; border-top: 1px solid #f0f0f1;
}
.tropk-actions .button-hero { padding: 8px 22px !important; height: auto !important; font-size: 15px !important; }

.tropk-badge {
	display: inline-flex; width: 26px; height: 26px; border-radius: 50%;
	align-items: center; justify-content: center; font-size: 16px;
	margin-right: 8px; vertical-align: middle;
}
.tropk-badge--ok { background: #00a32a; color: #fff; }
.tropk-badge--fail { background: #d63638; color: #fff; }

.tropk-step--ok h2 { color: #00712a; }
.tropk-step--fail h2 { color: #9a1a1a; }

.tropk-checks { width: 100%; margin: 18px 0; border-collapse: collapse; }
.tropk-checks td { padding: 8px 6px; border-bottom: 1px solid #f0f0f1; vertical-align: top; font-size: 14px; }
.tropk-checks tr:last-child td { border-bottom: 0; }
.tropk-checks__mark { width: 28px; font-weight: 700; font-size: 16px; }
.tropk-checks tr.ok .tropk-checks__mark { color: #00a32a; }
.tropk-checks tr.warn .tropk-checks__mark { color: #dba617; }
.tropk-checks tr.fail .tropk-checks__mark { color: #d63638; }
.tropk-checks__label { font-weight: 600; width: 36%; }
.tropk-checks__detail { color: #50575e; font-family: SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; word-break: break-all; }

.tropk-kv { margin: 0; }
.tropk-kv dt { font-weight: 600; margin-top: 10px; font-size: 12px; color: #50575e; text-transform: uppercase; letter-spacing: .03em; }
.tropk-kv dd { margin: 4px 0 0; }
.tropk-kv code { font-size: 12px; word-break: break-all; }

.tropk-note { font-size: 13px; color: #646970; margin-top: 18px; }

@media (max-width: 720px) {
	.tropk-choice-grid { grid-template-columns: 1fr; }
	.tropk-actions { flex-direction: column; align-items: stretch; }
	.tropk-actions form, .tropk-actions a, .tropk-actions button { width: 100%; }
}
</style>
CSS;
	}

	private function copy_script(): string {
		return <<<'JS'
<script>
(function(){
	document.querySelectorAll('.tropk-copy-btn').forEach(function(btn){
		btn.addEventListener('click', function(){
			// Two modes: copy from an <input> selected by data-copy-target,
			// or copy a literal string passed via data-copy-text (used by the
			// Windsurf JSON snippet, which is rendered as <pre><code>).
			var literal = btn.getAttribute('data-copy-text');
			var text = '';
			var input = null;
			if (literal !== null) {
				text = literal;
			} else {
				var sel = btn.getAttribute('data-copy-target');
				input = sel ? document.querySelector(sel) : null;
				if(!input) return;
				if (typeof input.select === 'function') {
					input.select();
					if (typeof input.setSelectionRange === 'function') {
						input.setSelectionRange(0, input.value.length);
					}
				}
				text = input.value != null ? input.value : input.textContent;
			}
			var original = btn.textContent;
			try {
				if(navigator.clipboard && navigator.clipboard.writeText){
					navigator.clipboard.writeText(text);
				} else if (input && typeof document.execCommand === 'function') {
					document.execCommand('copy');
				} else {
					// Fallback for non-input sources: temporary textarea.
					var ta = document.createElement('textarea');
					ta.value = text;
					ta.style.position = 'fixed';
					ta.style.opacity = '0';
					document.body.appendChild(ta);
					ta.select();
					document.execCommand('copy');
					document.body.removeChild(ta);
				}
				btn.classList.add('copied');
				btn.textContent = 'Copied ✓';
			} catch(e){
				btn.textContent = 'Press Ctrl+C';
			}
			setTimeout(function(){ btn.classList.remove('copied'); btn.textContent = original; }, 1800);
		});
	});
})();
</script>
JS;
	}

	private function brand_logo_svg(): string {
		// Simple geometric mark (gradient cube) — not the Tropk.ai trademark.
		return '<svg width="56" height="56" viewBox="0 0 56 56" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
			. '<defs><linearGradient id="tg1" x1="0" y1="0" x2="1" y2="1">'
			. '<stop offset="0" stop-color="#5b8def"/><stop offset="1" stop-color="#a06bff"/></linearGradient></defs>'
			. '<rect width="56" height="56" rx="14" fill="url(#tg1)"/>'
			. '<path d="M16 38V18h7l5 12 5-12h7v20h-5V25l-5 13h-4l-5-13v13z" fill="#fff" opacity=".95"/>'
			. '</svg>';
	}

	private function menu_icon_data_uri(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="#a7aaad">'
			. '<path d="M3 5h14v2H3zm0 4h14v2H3zm0 4h9v2H3z"/>'
			. '<circle cx="15.5" cy="14" r="2"/>'
			. '</svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Floating "Report an error" link, rendered on every onboarding step.
	 * Bottom-right corner. Pre-fills the subject so the email lands in
	 * the right inbox bucket.
	 */
	private function report_error_link(): string {
		$mailto = 'mailto:gardelin@tropk.ai?subject=' . rawurlencode( '[MCP for WP] Report an error' )
			. '&body=' . rawurlencode(
				"Hi Tropk.ai,\n\n"
				. "I hit an issue with MCP for WP.\n\n"
				. "Site URL: " . home_url( '/' ) . "\n"
				. "Plugin version: " . ( defined( 'TROPK_MCP_VERSION' ) ? TROPK_MCP_VERSION : 'unknown' ) . "\n"
				. "WordPress: " . ( $GLOBALS['wp_version'] ?? 'unknown' ) . "\n"
				. "PHP: " . PHP_VERSION . "\n\n"
				. "What I was trying to do:\n\n"
				. "What happened instead:\n\n"
			);

		$icon = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
			. '<path d="M21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3z"/>'
			. '<line x1="12" y1="9" x2="12" y2="13"/>'
			. '<line x1="12" y1="17" x2="12.01" y2="17"/>'
			. '</svg>';

		return '<a class="tropk-report" href="' . esc_attr( $mailto ) . '" aria-label="' . esc_attr__( 'Report an error', 'mcp-for-wordpress' ) . '">'
			. '<span class="tropk-report__icon" aria-hidden="true">' . $icon . '</span>'
			. '<span class="tropk-report__label">' . esc_html__( 'Report an error:', 'mcp-for-wordpress' ) . ' </span>'
			. '<span class="tropk-report__email">gardelin@tropk.ai</span>'
			. '</a>';
	}

}
