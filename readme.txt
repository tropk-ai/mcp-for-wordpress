=== MCP for WP, Elementor and more by Tropk.ai ===
Contributors: tropkai
Tags: mcp, ai, claude, chatgpt, oauth, elementor, rank-math, acf, woocommerce, seo
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.5.4
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
* ACF — **full coverage**: field VALUES (get/update/delete on posts, users, terms, options pages, blocks, comments — via ACF's value pipeline so images, repeaters, dates, relationships round-trip in the right shape); **schema** CRUD on field groups, fields, custom post types (Pro 6.1+) and taxonomies (Pro 6.1+); **admin actions** (activate/deactivate, trash/untrash, duplicate, import/export as PHP); **repeater + flexible_content** row-level ops (add/update/delete row, sub-rows); **Pro Options Pages** (code-registered + UI-registered Pro 6.2+); **ACF Blocks** (Gutenberg blocks defined by ACF fields); **introspection** of registered field types + location rule operators; **acf-json/ sync** for version-controlled field groups.
* Elementor — page outline, list widgets, get widget, clone page (with ID regen), search-and-replace, update setting, delete widget, flush CSS. **V4 atomic** is now writable: Global Classes + Variables CRUD, typed `settings` read/write on atomic elements, local `styles` upsert (breakpoint + state variants), schema introspection (`get-atomic-schema`, `get-style-schema`, `list-prop-types`).
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

= How does the plugin handle Elementor V4 (atomic) widgets? =

As of 0.5.22 V4 is writable: typed `settings` and local element `styles` are exposed via dedicated MCP tools (`get-atomic-settings`, `set-atomic-settings`, `get-element-styles`, `set-element-style`, `remove-element-style`), the design-system reuse layer (Global Classes + Variables) has full CRUD, and `get-atomic-schema` / `get-style-schema` / `list-prop-types` expose the per-widget props schema, CSS prop schema and `$$type` catalog so the model can author valid V4 data. The plugin calls Elementor's own repositories at runtime — it never poke meta keys directly — so order, label map, watermark and editor cache invalidation stay consistent with the editor.

== Changelog ==

= 0.5.4 =
This release consolidates three latent registration-order fixes that surfaced during a deep OAuth debugging session on a live VPS. Note up front: none of these patches was the root cause of the "could not register at login service" / "Não foi possível registrar no serviço de login" error that prompted the investigation — that error turned out to be operational (an nginx `location ~ /.well-known { try_files $uri =404; }` block that swallowed path-aware AS metadata requests before they reached WordPress, plus Elementor Maintenance Mode returning 503 on the anonymous `/tropk-mcp/oauth/authorize` consent page and `/wp-json/tropk-mcp/v1/mcp` session endpoint). Hosts hitting that symptom should re-check nginx routing and any maintenance-mode plugin before assuming the plugin itself is at fault. See the "Host configuration requirements" section below.

The three plugin-side fixes shipped here are real latent bugs surfaced by that session:

1. **Rewrite-flush priority race (was: `MetadataEndpoints::register_rewrites()`)**: the version-bump `flush_rewrite_rules()` ran inline at `init:10` immediately after registering the `.well-known/*` rules, but BEFORE `AuthorizationEndpoint::register_rewrite()` (also `init:10`, registered later in Bootstrap) had a chance to add its `^tropk-mcp/oauth/authorize/?$` rule. On hosts where the persisted `rewrite_rules` option had not been re-flushed since the rule was introduced, `/tropk-mcp/oauth/authorize` would 404 until the admin manually visited Settings → Permalinks. The flush is now a dedicated `maybe_flush_rewrites()` callback at `init:999`, so every component that adds a rewrite rule at the default `init:10` priority has registered by the time it fires.

2. **Abilities API hook-name bridge (was: `McpServerBootstrap::register()`)**: the vendored `wordpress/mcp-adapter` v0.5.x package registers its three core abilities (`mcp-adapter/discover-abilities`, `mcp-adapter/get-ability-info`, `mcp-adapter/execute-ability`) via `add_action('wp_abilities_api_init', …)` deferred inside `maybe_create_default_server()`, which is only reached during `rest_api_init:15`. On hosts where (a) WordPress core ships the Abilities API natively (WP 6.9+/7.0, fires `wp_abilities_api_init` during `init`), or (b) another plugin lazy-instantiates `WP_Abilities_Registry` during `plugins_loaded`, the action can fire BEFORE mcp-adapter's `add_action()` lands — leaving the three core abilities unregistered. `McpComponentRegistry` then logs `WordPress ability '…' does not exist` for each at server-creation time. The fix bridges BOTH name variants (`wp_abilities_api_init` for WP core, `abilities_api_init` for the vendor fallback) onto McpAdapter's public registration methods from `plugins_loaded:20`, plus a belt-and-braces synchronous fallback at `init:5` for the race where either action already fired by then. Respects the upstream `mcp_adapter_create_default_server` filter. Mirrors the double-hook pattern already used by `AbilityRegistrar` for our own abilities.

3. **Repo / release hygiene**: the public `tropk-ai/mcp-for-wordpress` repo was out of sync with the internal development mirror (last public push was 0.5.14, no tags, no GitHub releases). This release tags the public main and ships an installable zip via GitHub Releases so external evaluators can pull a known-good build without scraping the source tree. From this release onward, every version bump on the internal mirror is mirrored to the public repo with a matching tag and release artifact on the same day.

### Host configuration requirements (read this before reporting an OAuth bug)

The OAuth fall-through that motivated this release was misdiagnosed at length as a plugin bug when both root causes were in the host. Sites running the plugin behind common stacks should confirm:

* **Path-aware AS metadata discovery** — modern MCP brokers (Claude.ai, ChatGPT, Cursor) probe `/.well-known/oauth-authorization-server<resource-path>` per RFC 8414, not only the bare `/.well-known/oauth-authorization-server`. If your webserver short-circuits `/.well-known/<anything-else>` to a 404 (typical nginx `try_files $uri $uri/ =404` block, common LiteSpeed `.well-known` ACME-only block), the broker discovery 404s before the request reaches WordPress and the connector aborts with a generic "could not register" message. nginx fix: replace `location ~ /.well-known { try_files $uri $uri/ =404; }` with `location ~ ^/\\.well-known/oauth- { try_files $uri $uri/ /index.php?$args; }` or remove the catch-all entirely so WordPress's own rewrite rules (which the plugin registers) can match.

* **Anonymous-request gates** — the OAuth consent screen at `/tropk-mcp/oauth/authorize` AND the MCP transport endpoint at `/wp-json/tropk-mcp/v1/mcp` MUST be reachable without an authenticated session. Anything that intercepts unauthenticated frontend traffic — Elementor Maintenance Mode, "Coming Soon" / Under Construction plugins, basic-auth at the webserver layer, aggressive bot/UA firewalls, REST API hardeners that reject anonymous calls — will return a non-2xx (Elementor returns 503) for these endpoints and the broker aborts. Maintenance-mode plugins typically have a path-exclusion option; whitelist `/tropk-mcp/*` and `/.well-known/oauth-*` and `/wp-json/tropk-mcp/*` there.

* **Rewrite rule presence** — after upgrading the plugin to 0.5.4 or first installing, the version-bump flush should regenerate `rewrite_rules` automatically. If the broker reports a 404 at the authorize hop, confirm with `wp rewrite list | grep tropk-mcp/oauth` that `^tropk-mcp/oauth/authorize/?$` is listed; if not, run `wp rewrite flush --hard` once.

= 0.5.27 =
* Fix (critical): the early `/.well-known/` interceptor introduced in 0.5.18 could fatal at `plugins_loaded:1` on hosts where `$wp_rewrite` isn't initialized yet (calling `rest_url()` ran `using_index_permalinks()` on `null`). The fatal happened BEFORE ability registration, so the whole MCP surface (~250 tools) silently vanished from the connector — the only visible symptom was OAuth `/authorize` returning 404 in the browser. The early hook now bails out cleanly when `$wp_rewrite` isn't ready and catches any unexpected `Throwable` so the rest of the bootstrap survives. The downstream `parse_request` + REST handlers continue serving the same metadata, unchanged.

= 0.5.26 =
* ACF — full surface coverage (65 actions across 10 tools, up from 4 actions across 4 tools):
  - **Values** (new `acf-value`): get-value / get-values / get-field-object / update-value / delete-value / has-value. Routes through `get_field`/`update_field` so images, repeaters, dates, relationships return in the proper shape. Accepts any ACF target: numeric post id, `"user_X"`, `"term_X"`, `"options"`, `"<options_page_menu_slug>"`, `"comment_X"`, `"block_X"`.
  - **Repeater + flexible_content rows** (new `acf-row`): add-row / update-row / delete-row / count-rows + the sub-row variants. 1-based indices, `acf_fc_layout` for flexible-content layouts.
  - **Pro Options Pages** (new `acf-options-page`): list-options-pages, add-options-page / add-options-sub-page, set-options-page-menu, plus full CRUD on the Pro 6.2+ UI Options Pages.
  - **ACF Blocks** (new `acf-block`): list-blocks / get-block / register-block. Gutenberg blocks defined by ACF fields.
  - **Introspection** (new `acf-introspect`): list-field-types, get-field-type, list-location-rule-types, list-location-rule-operators, list-field-categories. Lets the model discover Pro/addon-registered field types before authoring.
  - **Local-JSON sync** (new `acf-local-json`): list-local-json, list-local-groups, save-to-json, get-load-paths, get-save-path, is-local-field-group, is-local-field. Pairs with the canonical version-control workflow for ACF field groups.
  - **Admin actions on the 4 schema tools**: activate / deactivate, trash / untrash, duplicate, export-as-php and import added to `acf-field-group`, `acf-post-type` and `acf-taxonomy`; `acf-field` gets `duplicate-field`. Same code paths the ACF admin UI uses (`acf_*_internal_post_type` family).

= 0.5.25 =
* Fix (ACF, critical): the four ACF schema tools (`acf-field-group`, `acf-field`, `acf-post-type`, `acf-taxonomy`) now call ACF's own PHP API (`acf_get_field_groups()`, `acf_get_acf_post_types()`, `acf_update_post_type()`, …) instead of routing through a dead `/wp-json/angie-acf-mcp/v1/*` REST bridge that was orphaned by the rewrite-to-original-code refactor. All four schema tools were returning "route not found" on every site since then — including the original repro on ACF Pro 6.8.
* New `AcfRuntime` detector throws clear errors when ACF or ACF Pro 6.1+ isn't installed. Identifiers accept slugs, ACF keys (`post_type_xyz`, `taxonomy_abc`) or numeric ACF post IDs. Updates merge over the existing definition so omitted keys keep their value (matches the ACF admin "save" semantics).

= 0.5.24 =
* Server-side onboarding for AI agents — the MCP `initialize` response now embeds a concise V4 atomic briefing (the `$$type` envelope, the schema-first workflow, the key tools). Any client that reads `instructions` (Claude.ai, Claude Desktop, ChatGPT, Cursor, mcp-inspector …) gets the V4 mental model before its first tool call, without needing a local skill.
* Ships a Claude Desktop / Claude Code skill at `.claude/skills/elementor-v4-mcp/SKILL.md` with the full playbook (typed-prop catalog, recipes, common pitfalls).

= 0.5.23 =
* Elementor V4 atomic — `add-atomic-element`: a single tool that creates any registered atomic widget (free + Pro) or container, picking the right node shape (widget vs container — `e-div-block` / `e-flexbox` / `e-grid` use the type as `elType`; widgets use `elType:"widget"` + `widgetType`). Supports nested children for containers, parent targeting + index, and runs settings/styles through `AtomicProps`. Replaces the need for one ability per `e-*` widget — Pro types (e-form-*, e-anchor, e-checklist) come along for free.

= 0.5.22 =
* Elementor V4 atomic — 8 more tools so the atomic surface is no longer opaque:
  - Schema introspection: `get-atomic-schema` (per-widget typed-props schema), `get-style-schema` (every CSS prop a style variant accepts + valid states + active breakpoints), `list-prop-types` (full `$$type` catalog).
  - Typed settings: `get-atomic-settings` and `set-atomic-settings` — read/write the V4 element's `settings` map with native typed-prop envelopes; JSON-string envelopes are normalized.
  - Local element styles: `get-element-styles`, `set-element-style` (upsert a CSS class scoped to one element, with per-breakpoint + state variants — auto-wires the style id into the element's `classes` prop), `remove-element-style` (also unwires the class).

= 0.5.21 =
* Elementor V4 design system — 13 new tools to author the V4 reuse layer that no MCP could previously touch:
  - Global Classes: `list-global-classes`, `get-global-class`, `create-global-class`, `update-global-class`, `delete-global-class`, `reorder-global-classes`, `set-global-class-props` (merge CSS props into a single breakpoint/state variant), `apply-global-class-to-element`.
  - Design-system Variables (Colors + Fonts): `list-variables`, `create-variable`, `update-variable`, `delete-variable` (soft-delete), `restore-variable`.
* Tools call Elementor's own `Global_Classes_Repository` and Variables `Storage\Repository` (loaded at runtime via `ElementorRuntime`) so order, label map, watermark and editor cache invalidation always stay consistent with the editor.
* Atomic typed-prop inputs (e.g. variant `props`) are normalized through `AtomicProps` — JSON-string envelopes are healed into native objects, no special-casing in each tool.

= 0.5.20 =
* Fix (Elementor V4, bug #2): `update-widget-setting` now stores typed atomic props (e.g. `{"$$type":"classes","value":["mb-x"]}`) as native objects even when the client delivers them as a JSON string, instead of persisting a stringified value the atomic schema parser rejects. Introduces the shared `AtomicProps` typed-prop builder/normalizer the V4 tooling is built on.

= 0.5.19 =
* Fix (Elementor V4, critical): `update-page-settings`, `import-page` and `meta-update-post-meta` now write `_elementor_page_settings` as a native PHP array instead of a JSON string. Writing JSON made Elementor's `get_saved_settings()` array-access a string and return a 500. All page-settings (de)serialization now funnels through a single `ElementorMeta` helper so the `_elementor_data` (JSON) vs `_elementor_page_settings` (array) formats can't be confused again.

= 0.5.18 =
* Intercept `/.well-known/oauth-protected-resource`, `/.well-known/oauth-authorization-server` and `/.well-known/openid-configuration` at `plugins_loaded` priority 1 — before WordPress's canonical redirect can turn them into a 301 to the homepage. Fixes OAuth discovery on Hostinger HCDN, KingHost-style nginx defaults and other Brazilian shared hosts that proxy `/.well-known/` through to WordPress.

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
