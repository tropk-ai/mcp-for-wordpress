<?php
declare(strict_types=1);

namespace Tropk\Mcp\OAuth;

use Tropk\Mcp\OAuth\Endpoints\AuthorizationEndpoint;
use Tropk\Mcp\OAuth\Endpoints\MetadataEndpoints;
use Tropk\Mcp\OAuth\Endpoints\RegistrationEndpoint;
use Tropk\Mcp\OAuth\Endpoints\RevocationEndpoint;
use Tropk\Mcp\OAuth\Endpoints\TokenEndpoint;
use Tropk\Mcp\OAuth\Endpoints\WellKnownStaticFiles;

final class Bootstrap {

	public function register(): void {
		( new BearerAuthenticator() )->register();
		// ChatGptCompat intentionally NOT registered. Its 401→JSON-RPC
		// rewrite breaks Claude.ai's DCR flow even when narrowed to /mcp,
		// because Claude inspects pieces of the response WP shipped before
		// our filter runs (or some other interaction we haven't pinned
		// down yet). Until ChatGPT is moved out of "unstable dev mode" in
		// the onboarding wizard and the rewrite is properly isolated, we
		// keep the file in tree so the prior research isn't lost, but the
		// runtime stays vanilla MCP-spec compliant — which is what Claude
		// already worked with.
		( new MetadataEndpoints() )->register();
		( new WellKnownStaticFiles() )->register();
		( new RegistrationEndpoint() )->register();
		( new AuthorizationEndpoint() )->register();
		( new TokenEndpoint() )->register();
		( new RevocationEndpoint() )->register();

		if ( ! wp_next_scheduled( 'tropk_mcp_oauth_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'tropk_mcp_oauth_cleanup' );
		}
		add_action( 'tropk_mcp_oauth_cleanup', [ $this, 'cleanup' ] );
	}

	public function cleanup(): void {
		( new AuthorizationCodes() )->purge_expired();
		( new Tokens() )->purge_expired();
	}
}
