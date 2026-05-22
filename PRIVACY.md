# Privacy policy — MCP for WP by Tropk.ai

**TL;DR — the plugin does not phone home, send analytics, or transmit your data to any third party.** Everything stays on your WordPress site, exposed only to MCP clients you explicitly authorise.

This document covers what data the plugin processes, where it lives, who has access, and what your responsibilities are under the GDPR / CCPA / LGPD when you connect an AI assistant to your site.

---

## 1. What the plugin does

MCP for WP turns a WordPress site into a Model Context Protocol (MCP) server. AI assistants that you connect — Claude.ai, ChatGPT, Cursor, Windsurf, mcp-inspector, Claude Desktop, Claude Code — call HTTP endpoints exposed by the plugin to read and write content, configuration, media and SEO data on the site.

Nothing in the plugin makes outbound calls to Tropk.ai, to any analytics provider, or to any third party except:

- the AI assistants the site administrator explicitly connects via OAuth, AND
- WordPress core update services (the normal `api.wordpress.org` pings that WordPress itself does — out of the plugin's control).

---

## 2. Data stored on the WordPress site

All of the following lives inside the WordPress database and uploads directory of the site that has the plugin installed. None of it leaves that site.

| Data | Where it lives | Why |
|---|---|---|
| OAuth client registrations | `{prefix}mcp_oauth_clients` table | so Claude.ai / ChatGPT can authenticate without an API key |
| Access + refresh tokens (SHA-256 hashed) | `{prefix}mcp_oauth_tokens` table | so each request from the connected client can be authenticated |
| Single-use authorisation codes | `{prefix}mcp_oauth_codes` table | RFC 6749 §4.1 flow — deleted within 10 minutes |
| Audit log of every tool invocation | `{prefix}mcp_audit_log` table | so the administrator can see exactly what the AI did |
| Pre-mutation post snapshots | `wp-content/uploads/mcp-backups/Y/m/d/*.json` | so destructive operations can be rolled back |
| OAuth discovery JSON | `.well-known/oauth-protected-resource`, `.well-known/oauth-authorization-server` (static files in the site root) | so MCP clients can discover the OAuth flow |
| Plugin settings | `tropk_mcp_allowed_origins`, `tropk_mcp_db_version`, `tropk_mcp_well_known_state`, `tropk_mcp_rewrites_flushed` options | runtime configuration |

Token material is never stored in plaintext; only the SHA-256 hash. Reverting from hash to plaintext is computationally infeasible.

The audit log redacts known sensitive keys (`password`, `pass`, `pwd`, `token`, `access_token`, `refresh_token`, `secret`, `authorization`, `api_key`, `apikey`, `client_secret`) automatically. Anything inside those keys appears as `[REDACTED]` in the log.

---

## 3. Data shared with AI assistants you connect

When you authorise an AI assistant via the OAuth flow, that assistant receives:

- An **access token** scoped to this site, with the WordPress capabilities of the user who clicked "Allow". The token grants the same level of access that user would have if they signed in via wp-admin.
- The **subset of tools** the access token's scope permits (read-only / write / destructive).
- The **data returned by tool calls** the AI assistant makes — this is the same data a logged-in WordPress user with that role could see.

The AI assistant then has its own privacy policy that governs what it does with that data once it has received it:

- **Anthropic (Claude.ai, Claude Desktop, Claude Code):** https://www.anthropic.com/privacy
- **OpenAI (ChatGPT):** https://openai.com/policies/privacy-policy
- **Google (Gemini):** https://policies.google.com/privacy
- **Cursor:** https://cursor.com/privacy
- Other MCP clients ship their own policies.

The plugin has no insight into what an AI assistant does with returned data after delivery. The site administrator is responsible for understanding the destination provider's policy before connecting.

---

## 4. Connection lifecycle

| Event | Effect on stored data |
|---|---|
| Plugin install / activate | Creates the four tables above; no data populated |
| Site administrator opens the onboarding wizard | No tokens issued yet |
| Site administrator clicks "Link" in the AI assistant | The assistant registers via DCR (RFC 7591) → row in `mcp_oauth_clients` |
| Site administrator approves on the consent screen | Authorisation code issued (single-use, expires in 10 min) |
| Assistant exchanges code for tokens | Access + refresh tokens stored as SHA-256 hashes |
| Assistant calls a tool | Audit log row written; if the tool is destructive, a post snapshot is written first |
| Site administrator revokes the connection (assistant UI, or admin can DELETE rows) | Tokens flagged `revoked=1`; client row remains for audit |
| Plugin deactivated | Cron cleanup jobs unscheduled; data stays |
| Plugin uninstalled | All four tables dropped, `wp-content/uploads/mcp-backups/` and `.well-known/oauth-*` files deleted, options removed |

---

## 5. Data retention

| Data | Default retention | How to change |
|---|---|---|
| Audit log | 90 days, then auto-pruned daily | filter `tropk_mcp_audit_retention_days`, returns days int |
| Post snapshots | Indefinite (no auto-cleanup) | delete `wp-content/uploads/mcp-backups/` subfolders by hand |
| Authorisation codes | 10 minutes (used or unused) | hard-coded; not configurable |
| Access tokens | 1 hour | hard-coded; not configurable |
| Refresh tokens | 14 days, rotated on every use | hard-coded; not configurable |
| Revoked tokens | Kept indefinitely so refresh-token-reuse detection works | filter or manual SQL `DELETE` |

---

## 6. Your responsibilities under the GDPR / CCPA / LGPD

If your site is subject to a privacy regulation, you (the site administrator) are responsible for:

1. **Disclosing the AI integration in your privacy notice.** Visitors / customers whose data the AI assistant could read are entitled to know that an external processor (the AI assistant provider) may access that data.
2. **Choosing an AI assistant whose data-processing terms you accept.** Anthropic and OpenAI both offer enterprise / "no-training" plans that contractually prevent your data from being used to train models; if you handle EU PII / health data / payment data, use one of those plans.
3. **Restricting the scope of the connection.** Use a WordPress user role that grants only the capabilities the AI actually needs (e.g. an editor for content tools, not an administrator). The OAuth token inherits that role's capabilities exactly.
4. **Reviewing the audit log periodically.** Settings → Wordpress MCP → audit log view shows every tool the AI has invoked.
5. **Revoking when in doubt.** Disconnecting in the AI assistant's UI revokes the refresh token; the access token expires within an hour after.

---

## 7. Children's data

The plugin does not interact with end-site visitors directly. It only exposes site-internal data to authenticated AI clients connected by an administrator. If your site stores data from children under the age of 13 (US COPPA) / 16 (EU GDPR), do not connect an AI assistant whose privacy policy permits storage of children's data.

---

## 8. Reporting a privacy issue

Open an issue at https://github.com/tropk-ai/mcp-for-wordpress or email security@tropk.ai. Reproducible privacy / security reports are triaged ahead of feature requests.

---

## 9. Updates to this policy

This document lives in the plugin's repository and ships with every release. The plugin's `readme.txt` Changelog flags releases that change privacy-relevant behaviour. The "last updated" date is the date of the release on which this file was last touched.

— Tropk.ai
