=== MCP for WP, Elementor and more by Tropk.ai ===
Contributors: tropkai
Tags: mcp, ai, claude, chatgpt, oauth, elementor, rank-math, acf, woocommerce, seo
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.5.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Turn any WordPress site into a Model Context Protocol (MCP) server for Claude.ai, ChatGPT, Cursor, Gemini and other AI assistants. 100% original code.

== Description ==

MCP for WP turns your WordPress site into a Model Context Protocol server. Any MCP-compatible AI assistant — Claude.ai, Claude Desktop, Claude Code, ChatGPT, Cursor, Windsurf, Gemini, mcp-inspector — connects with one click and uses hundreds of pre-built tools to read, write and orchestrate the site.

= 100% original code =

Every ability is authored by Tropk.ai. No third-party plugin source is bundled. The only third-party code that ships with the plugin is the small set of Composer dependencies that WordPress 6.9 + the MCP spec require: `wordpress/abilities-api`, `wordpress/mcp-adapter`, `wordpress/php-mcp-schema` and `automattic/jetpack-autoloader` — all GPL-2.0-or-later and pulled from packagist.org.

= Onboarding wizard =

After activation, a 3-step wizard opens:

1. Pick your AI assistant (Claude / ChatGPT)
2. Paste a URL into the assistant's Custom-connector screen and sign in
3. Click "Test connection" — the wizard verifies the MCP endpoint, the OAuth discovery URLs, the tools count, and whether unauthenticated requests return the OAuth challenge header

No API keys, no Authorization headers, no Application Passwords required. OAuth 2.1 with Dynamic Client Registration does everything.

= What's exposed =

* Posts, pages, custom post types — list / get / create / update / delete / bulk
* Comments — list / get / reply / approve / spam / trash
* Users + roles — full CRUD + capabilities
* Menus + classic widgets
* Media library — list / upload from URL / upload base64 / update / delete
* Taxonomies and terms
* Site options (allowlisted) + safe environment introspection
* Plugins — install from wordpress.org / activate / deactivate / delete
* Performance — purge cache for WP Rocket, LiteSpeed, W3TC, WP Super Cache, Cache Enabler, Autoptimize
* SEO — on-page audit (DOM parser), Rank Math meta + schemas, Internal Link Juicer keyword + orphan tools
* ACF — read field values via `get_field()`, update via `update_field()`, list field groups
* Elementor — page outline, list widgets, get widget, clone page (with ID regen), search-and-replace, update setting, delete widget, flush CSS. Atomic widgets (V4) are detected and treated as opaque.
* Gutenberg / FSE — reusable blocks, block patterns, style variations
* WooCommerce — when active
* Cron, transients, database introspection, shortcodes, debug log

Every destructive tool requires a custom `mcp_invoke_destructive_tools` capability AND snapshots the affected post before writing.

= Security =

* Per-request `wp_set_current_user` rebind — closes the "confused deputy" class of MCP vulnerabilities
* Origin allowlist — mitigates the DNS-rebinding + CSRF class behind CVE-2025-49596
* Three-bucket token-bucket rate limiter (per-IP, per-user, per-destructive-tool)
* Atomic Elementor (V4) widgets are never decoded — clones preserve their JSON verbatim
* OAuth tokens stored only as SHA-256 hashes; refresh-token reuse revokes the entire lineage
* Sensitive-key redaction in the audit log

= Privacy =

The plugin does not phone home, send analytics, or transmit data to Tropk.ai. Everything stays on your WordPress site. See PRIVACY.md in the plugin folder for the full privacy policy.

== Installation ==

1. Upload via Plugins → Add New → Upload Plugin → choose the zip → Install Now
2. Activate
3. The wizard opens automatically — pick Claude / ChatGPT, paste the URL into the AI's Custom-connector dialog, sign into WordPress, click Allow, click Test

Requirements: WordPress 6.9+, PHP 8.1+.

== Frequently Asked Questions ==

= Does it work on WordPress 6.8 or older? =

No. The plugin requires the Abilities API that landed in WordPress 6.9 core (December 2025), plus PHP 8.1+.

= Do I need an API key or Application Password? =

No. Claude.ai and ChatGPT use OAuth 2.1 with Dynamic Client Registration — credentials are handled automatically. For Claude Code / Claude Desktop / Cursor you can still use an Application Password if you prefer.

= Is data sent to Tropk.ai? =

No. Read PRIVACY.md.

= Why are some Elementor V4 (atomic) widgets read-only? =

Atomic widgets use an internal schema that Elementor reserves the right to change between minor versions. This plugin never decodes them, never generates their JSON from scratch, and never edits them structurally — only cloning is permitted and the JSON is preserved verbatim.

== Changelog ==

= 0.5.1 =
* Plugin rewritten from scratch — 100% original code, no bundled third-party plugins.
* Renamed to "MCP for WP, Elementor and more by Tropk.ai".
* Onboarding wizard refreshed (English) with the OAuth-only flow that Claude.ai and ChatGPT actually use.
* New ability surfaces: Posts CRUD, Comments, Users, Menus, Media (incl. base64 upload), Options, Meta, System, Widgets, Plugins, Taxonomies, Elementor (read + write).
* OAuth 2.1 — Dynamic Client Registration, PKCE S256, refresh-token rotation with reuse detection, revocation.
* PRIVACY.md added.
* Translations: pt_BR and es_ES.

== License ==

GPL v3.0-or-later. The "MCP for WP by Tropk.ai" name and brand assets are trademarks of Tropk.ai; the GPL applies to the source code only.
