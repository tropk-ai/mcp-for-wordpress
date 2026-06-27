# Wordpress MCP by Tropk.ai

Turn any WordPress site into a Model Context Protocol (MCP) server for Claude.ai, ChatGPT, Cursor, Windsurf, mcp-inspector and other AI assistants. Built on the official WordPress 6.9 Abilities API. **500+ tools** out of the box.

## What it does

- Single endpoint at `/wp-json/webinhood-mcp/v1/mcp`
- Full OAuth 2.1 (Dynamic Client Registration, PKCE S256, refresh-token rotation, revocation) — no API keys, no Authorization headers
- `500+` abilities across: Posts / Pages / CPTs, Elementor (161), Rank Math (23), ACF, WooCommerce (18), Internal Link Juicer, Gutenberg/FSE, cache, cron, security, roles, media
- Visual onboarding wizard — pick the AI assistant, paste a URL, click "test"
- Audit log + per-post snapshots before any destructive write
- Confused-deputy guard, Origin allowlist, three-bucket rate limiter, atomic-widget safety

## Install

1. Download the latest release zip
2. WP Admin → Plugins → Add New → Upload Plugin → choose the zip → Install Now → Activate
3. The wizard opens automatically — follow the 3 steps

Requirements: WordPress 6.9+, PHP 8.1+.

## Connecting

The wizard walks you through it. The short version:

- In Claude.ai or ChatGPT, open **Settings → Connectors → Add custom connector**
- Paste `https://YOUR-SITE/wp-json/webinhood-mcp/v1/mcp`
- Click "Link", sign in to WordPress, click "Allow"

That's it. OAuth handles the rest.

## Licensing

The plugin's source code is **GPL-3.0-or-later**. You can use, modify, redistribute and (per GPL) commercially exploit the code, provided derivative works stay GPL.

The name **"Wordpress MCP by Tropk.ai"** and the Tropk.ai brand marks are trademarks of Tropk.ai. Forks/derivatives must rename — please don't ship a clone under our brand.

Bundled third-party packages retain their own licenses:

| Package | License |
|---|---|
| `wordpress/abilities-api` | GPL-2.0-or-later |
| `wordpress/mcp-adapter` | GPL-2.0-or-later |
| `wordpress/php-mcp-schema` | GPL-2.0-or-later |
| `msrbuilds/elementor-mcp` (vendored) | GPL-3.0-or-later |
| `bjornfix/*` (vendored) | GPL-2.0-or-later |
| `angie/acf` (vendored) | GPL-3.0-or-later |
| `automattic/jetpack-autoloader` | GPL-2.0-or-later |

## Contributing

Issues + PRs welcome at https://github.com/tropk-ai/wordpress-mcp. If you fork, please open an issue first so we can avoid duplicate work.

## Credits

See `CREDITS.md` for the vendored sources and their authors.
