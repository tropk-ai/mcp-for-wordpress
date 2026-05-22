# Credits

**MCP for WP, Elementor and more by Tropk.ai** is original PHP authored by Tropk.ai. The plugin uses these Composer dependencies (installed via `composer install`):

| Package | License | What it does |
|---|---|---|
| `wordpress/abilities-api` | GPL-2.0-or-later | The Abilities API foundation that ships in WordPress 6.9+ core. Used to declare and register tools. |
| `wordpress/mcp-adapter` | GPL-2.0-or-later | Wraps registered abilities into a Model Context Protocol server (Streamable HTTP transport). |
| `wordpress/php-mcp-schema` | GPL-2.0-or-later | Typed DTOs for MCP spec primitives. |
| `automattic/jetpack-autoloader` | GPL-2.0-or-later | Conflict-resilient autoloader so the plugin can coexist with other plugins using the same dependencies. |

These are dependencies in the standard sense — `composer install` fetches them from packagist.org at deploy time. Nothing is forked, no third-party plugin source code is copied into this repository.

## Standards followed

* WordPress 6.9 Abilities API (December 2025 release)
* Model Context Protocol spec **2025-11-25** with negotiation down to 2025-06-18 and 2024-11-05
* OAuth 2.1 (RFC 6749 + draft-ietf-oauth-v2-1)
* PKCE S256 (RFC 7636)
* Dynamic Client Registration (RFC 7591)
* OAuth Authorization Server Metadata (RFC 8414)
* OAuth Protected Resource Metadata (RFC 9728)
* Resource Indicators for OAuth (RFC 8707)
* OAuth 2.0 Security Best Current Practice — refresh-token rotation + reuse detection (RFC 9700)
* OAuth 2.0 Token Revocation (RFC 7009)
* JSON Schema draft 2020-12

## Trademarks

"MCP for WP by Tropk.ai", "Tropk.ai" and any associated brand marks are trademarks of Tropk.ai. The plugin source is GPL-3.0-or-later; the trademarks are not.

"WordPress", "Elementor", "Rank Math", "Advanced Custom Fields" (ACF), "WooCommerce", "Internal Link Juicer", "Claude", "ChatGPT", "Gemini", "Cursor" and "Windsurf" are trademarks of their respective owners. This plugin is not affiliated with, endorsed by, or sponsored by any of them.

## Contributors

— Tropk.ai team.

External contributors: see https://github.com/tropk-ai/mcp-for-wordpress/graphs/contributors.

## Reporting security issues

security@tropk.ai or via a private security advisory on the GitHub repo.
