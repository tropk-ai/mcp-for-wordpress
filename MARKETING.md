# wp.org plugin profile description — MCP for WP, Elementor and more by Tropk.ai

Paste this into the long-description field on the wordpress.org submission form. Markdown is accepted on the plugin directory.

---

## Short description (one line, ≤ 150 chars)

> Connect any WordPress site to Claude, ChatGPT, Cursor and Gemini through MCP. ~400 tools across content, Elementor, SEO, WooCommerce and more.

---

## Long description

**MCP for WP, Elementor and more by Tropk.ai** turns any WordPress site into a Model Context Protocol (MCP) server. Once installed, any MCP-compatible AI assistant — Claude.ai, ChatGPT, Claude Desktop, Claude Code, Cursor, Windsurf, Gemini, mcp-inspector — can connect with one click and use **hundreds of pre-built tools** to read, write and orchestrate the site.

No API keys. No copy-pasted headers. The visual 3-step wizard does the OAuth handshake for you.

This plugin is the spiritual successor to a year of fragmented "WordPress + AI" plugins — every tool is **100% our own code**, written from scratch on the official WordPress 6.9 Abilities API. Nothing is bundled, nothing is forked, no third-party plugin source is shipped.

### What you can do with it

**Content management**
- List, read, create, update, delete posts, pages and custom post types
- Bulk operations — generate 50 product descriptions, slugify 200 titles, retroactively assign categories
- Edit individual postmeta keys with snapshot-backed rollback
- Manage comments, taxonomies, terms, menus, media
- Schedule posts, set featured images, draft from outline

**Web design (Elementor)**
- Get a compact outline of any Elementor page (≤ 2 KB) so the AI can reason about structure without burning context
- List every widget on a page with id, type, depth and a text snippet
- Clone a page with all element IDs regenerated and Theme Builder + Global Kit carried over
- Search-and-replace text across an Elementor page in a single call
- Update a widget setting by ID (single key) — no need to ship 100 KB of JSON
- Delete a widget surgically without breaking the surrounding container hierarchy
- Atomic widgets (Elementor 4.0+ V4) are detected automatically and treated as opaque — cloned verbatim, never decoded, never edited structurally
- Flush Elementor's pre-generated CSS per-post or globally

**SEO (Rank Math + on-page audit)**
- Read and write Rank Math meta (title, description, focus keyword, canonical, robots, social cards) via the `/rankmath/v1/updateMeta` REST when Headless CMS Support is enabled, or direct postmeta otherwise
- Write JSON-LD schemas (Article, Product, FAQ, HowTo, Review, Recipe, JobPosting, Event, NewsArticle …)
- Read Rank Math's rendered `<head>` HTML for any URL
- Run a structured on-page audit (DOMDocument parser): title length, meta description, canonical, hreflang, OG / Twitter cards, headings hierarchy, image alt coverage, internal/external link ratio, JSON-LD presence, word count — returned as a tagged `issues[]` array with a 0-100 score

**Marketing**
- Bulk-generate meta descriptions for hundreds of posts at once
- Find orphan pages (no incoming internal links) via Internal Link Juicer integration
- Set internal-linking keywords on any post and trigger an ILJ reindex
- Audit Open Graph / Twitter Card coverage across a list of URLs
- Compare a page's SEO state before / after a campaign via per-tool checkpoints

**Custom fields (ACF)**
- Read every ACF field value on a post through `get_field()` so Repeater and Flexible Content come back as nested structures, not raw postmeta
- Update individual ACF fields via `update_field()` — safe for repeaters, flexible content, and field-key references
- List ACF field groups, field types, post types and taxonomies

**Performance**
- Purge cache for WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, Cache Enabler and Autoptimize through their documented public APIs (never their internals)
- Audit Core Web Vitals via Google PageSpeed Insights (BYO key)
- Optimise images, defer scripts, manage lazy-load behaviour

**Site administration**
- Manage users, roles, capabilities
- Install / activate / deactivate / delete plugins
- Read debug.log, inspect transients, surface WP_DEBUG state
- CRUD-style menus, sidebars, classic widgets
- Inspect database tables and run safe SELECT queries
- Reset and clean transients, options, expired comments

**WooCommerce** (when active)
- Read and update products, orders, customers, coupons
- Bulk price adjustments, inventory edits

**Gutenberg / FSE**
- Read and write reusable blocks, block patterns, style variations
- Manage template parts and theme.json

### Use cases

**SEO operator** — "Audit my last 50 published posts; find ones missing meta descriptions, generate them based on the post body, and apply." MCP for WP lets the AI call `seo-audit-onpage`, then `seo-update-meta` in a loop, with full audit trail.

**Web designer** — "Clone this homepage into a new landing page, replace 'spring sale' with 'summer sale' across all widgets, swap the hero image." The AI calls `elementor-clone-page`, `elementor-replace-text`, `elementor-update-widget-setting`. Every step is snapshotted.

**Content marketer** — "Generate 20 blog post outlines based on the brand voice, save them as drafts, and queue them for review." The AI calls `posts-create` 20 times with status=draft. Bulk generation in one go.

**Agency operator** — "Onboard a new client site. Read existing posts, audit SEO, propose new sitemap structure, set redirections." Multi-tool workflow over a single OAuth connection.

**Developer** — "Run `wp eval` to test a snippet, then check the debug log, then update a single ACF field." MCP for WP exposes the moving parts that previously required SSH access.

### Security

- Every destructive tool requires a custom `mcp_invoke_destructive_tools` capability AND snapshots the affected post first
- Per-request `wp_set_current_user` rebind — closes the "confused deputy" class of MCP vulnerabilities
- Three-bucket token-bucket rate limiter (per-IP, per-user, per-destructive-tool)
- Origin allowlist — mitigates the DNS-rebinding + CSRF class behind CVE-2025-49596
- Atomic Elementor (V4) widgets are never decoded — clones preserve their JSON verbatim
- OAuth 2.1 with Dynamic Client Registration (RFC 7591), PKCE S256, refresh-token rotation with reuse detection (RFC 9700), revocation (RFC 7009)
- Tokens stored only as SHA-256 hashes; refresh-token reuse revokes the entire lineage
- Sensitive-key redaction in the audit log

### Installation

1. Upload the plugin zip via Plugins → Add New → Upload Plugin → choose the file → Install Now
2. Activate
3. The wizard opens automatically. Pick Claude.ai or ChatGPT. Paste the URL into the AI's "Custom connector" screen. Sign in to WordPress. Click "Allow". Click "Test connection". Done.

Requirements: WordPress 6.9+, PHP 8.1+.

### Pricing

Free. GPL-3.0. We sell consulting, custom integrations and managed setup at https://tropk.ai — the plugin itself is and always will be free.

### Trademarks

"MCP for WP by Tropk.ai" and "Tropk.ai" are trademarks of Tropk.ai. Forks are welcome under the GPL but please rename them. "WordPress", "Elementor", "Rank Math", "ACF", "WooCommerce", "Claude", "ChatGPT" and "Gemini" are trademarks of their respective owners; this plugin is not affiliated with or endorsed by any of them.
