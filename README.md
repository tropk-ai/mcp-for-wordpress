# MCP for WP, Elementor and more by Tropk.ai

Turn any WordPress site into a Model Context Protocol (MCP) server for Claude.ai, Lovable, ChatGPT (instable), Cursor, Windsurf, and other AI assistants. Built on the official WordPress 6.9 Abilities API. **450+ tools** out of the box, with 100% original code.

## What it does

- **Single Endpoint**: Exposes a unified endpoint at `/wp-json/tropk-mcp/v1/mcp`
- **Full OAuth 2.1**: Secure connection using Dynamic Client Registration, PKCE S256, refresh-token rotation, and revocation — no API keys or static Authorization headers required.
- **450+ Abilities**: Out-of-the-box support for:
  - **Posts, Pages & CPTs**: Full CRUD, bulk operations, and metadata.
  - **Elementor**: Page outline, listing and reading widgets, cloning pages with ID regeneration, search-and-replace, updating settings, deleting widgets, and flushing CSS.
  - **SEO**: On-page audit (DOM parser), Rank Math meta & schemas, and Internal Link Juicer keyword & orphan tools.
  - **ACF**: Read field values via `get_field()`, update via `update_field()`, and list field groups.
  - **WooCommerce**: Full capabilities when active.
  - **Gutenberg / FSE**: Reusable blocks, block patterns, and style variations.
  - **Media Library**: List, upload from URL, upload base64, update, and delete.
  - **Users & Roles**: Full CRUD and capability management.
  - **Performance/Cache**: Purge cache for WP Rocket, LiteSpeed, W3TC, WP Super Cache, Cache Enabler, and Autoptimize.
  - **System & Utilities**: Menus, classic widgets, taxonomies, cron, transients, database introspection, shortcodes, and debug log.
- **Onboarding Wizard**: Step-by-step visual setup — pick your AI assistant, paste the URL, and click "test" to verify the connection.
- **Safety First**: Audit logging, per-post snapshots before destructive writes, a confused-deputy guard, origin allowlist, and three-bucket rate limiter.

## Install

1. Download the latest release zip.
2. Go to **WP Admin → Plugins → Add New → Upload Plugin**, choose the zip, click **Install Now**, and then **Activate**.
3. The onboarding wizard opens automatically — follow the 3 steps to connect.

*Requirements: WordPress 6.9+, PHP 8.1+.*

## Connecting

The wizard walks you through the entire process. In short:

1. In Claude.ai or ChatGPT, open **Settings → Connectors → Add custom connector**.
2. Paste your endpoint: `https://YOUR-SITE/wp-json/tropk-mcp/v1/mcp`
3. Click **Link**, sign in to your WordPress site, and click **Allow**.

That's it! OAuth handles the rest securely.

## Licensing

The plugin's source code is licensed under the **GPL-3.0-or-later**. You can use, modify, redistribute, and commercially exploit the code under the terms of the GPL, provided derivative works remain under the GPL.

The name **"MCP for WP, Elementor and more by Tropk.ai"** and the Tropk.ai brand marks are trademarks of Tropk.ai. Forks and derivatives must be renamed — please do not distribute clones under our brand name.

### Bundled Packages

The only third-party code shipped with this plugin is a small set of Composer dependencies required by WordPress 6.9 and the MCP specification:

| Package | License | Description |
|---|---|---|
| `wordpress/abilities-api` | GPL-2.0-or-later | Standard API for managing system abilities |
| `wordpress/mcp-adapter` | GPL-2.0-or-later | Bridge between WordPress abilities and the MCP protocol |
| `wordpress/php-mcp-schema` | GPL-2.0-or-later | PHP implementation of the Model Context Protocol schema |
| `automattic/jetpack-autoloader` | GPL-2.0-or-later | Optimized class autoloader |

## Contributing

Issues and Pull Requests are welcome at https://github.com/tropk-ai/mcp-for-wordpress. If you are planning a fork or major change, please open an issue first to discuss it with us.

## Credits

See `CREDITS.md` for details about vendored libraries, specifications, and their respective authors.

