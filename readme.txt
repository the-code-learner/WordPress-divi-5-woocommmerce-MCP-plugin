=== MCP Bridge for Divi 5 and WooCommerce ===
Contributors: TODO-wordpress-org-username
Tags: mcp, divi, woocommerce, ai, automation
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 0.1.9
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure MCP foundations for WordPress, Divi 5, WooCommerce, browser-based preview, and controlled publishing workflows.

== Description ==

MCP Bridge for Divi 5 and WooCommerce is an early-stage plugin that builds on the WordPress Abilities API and the official WordPress MCP Adapter.

Version 0.1.9 fixes a live RFC 9728 interoperability failure discovered against the development site: the path-inserted protected-resource metadata URL returned the correct JSON body with HTTP 404 because WordPress had already classified the direct well-known path as not found before the plugin intercepted it. The plugin now sends an explicit HTTP 200 for its OAuth metadata responses. OAuth discovery documents are also marked non-cacheable, including through LiteSpeed Cache's public no-cache hook, and stale metadata URLs are purged once per plugin version so old OAuth metadata or cached 404 responses cannot survive an update.

Version 0.1.8 aligns ChatGPT token exchange with the public-client OAuth path implemented by the pinned OAuth server. Authorization-server metadata now advertises only `token_endpoint_auth_method=none`, which ChatGPT supports through its CIMD method intersection, so Authorization Code + PKCE can proceed without the custom `private_key_jwt` preflight. The same release validates ChatGPT's exact MCP `resource` parameter on both authorization and token requests; access tokens remain audience-bound to `/wp-json/mcp/mcp-oauth-server`.

Version 0.1.7 fixes MCP tool discovery on WordPress 6.9 when the pinned WordPress MCP Adapter 0.6.1 registers its three shared abilities after the one-shot Abilities API initialization window. The plugin now idempotently ensures `mcp-adapter/discover-abilities`, `mcp-adapter/get-ability-info`, and `mcp-adapter/execute-ability` are registered in time for both the existing WordPress-authenticated server and the OAuth server.

Version 0.1.6 added an experimental RS256 `private_key_jwt` verifier for ChatGPT token-endpoint client authentication and the RFC 9728 path-inserted protected-resource metadata URL. Version 0.1.8 no longer advertises or registers that experimental verifier in the runtime token path because the embedded token endpoint natively implements the standards-supported `none` + PKCE public-client flow.

Version 0.1.5 fixes ChatGPT OAuth client resolution when ChatGPT's Client ID Metadata Document prefers `private_key_jwt` but also advertises the public-client method `none`. The compatibility layer normalizes the fetched ChatGPT CIMD only for upstream authorization-time validation when `none` is explicitly supported by the client.

Version 0.1.4 adds an OAuth 2.1 authentication path for remote MCP clients such as ChatGPT while retaining the existing WordPress-authenticated MCP endpoint. OAuth clients connect to `/wp-json/mcp/mcp-oauth-server`, discover the authorization server from the site's OAuth metadata, authorize through the normal WordPress login flow, and use Authorization Code with PKCE S256. The OAuth layer issues bearer access tokens and rotating refresh tokens and supports revocation.

The OAuth endpoint is enabled only when the canonical WordPress Site Address uses HTTPS. WordPress Application Passwords are used only as internal, revocable session anchors by the OAuth implementation: their raw values are discarded immediately and are not entered into the MCP client, placed in URLs, or exposed by this plugin.

Version 0.1.3 added plugin-scoped MCP update operations for the current development cycle. An authenticated MCP client can force a fresh stable GitHub release check, read the current and available plugin versions, and update only this plugin when the caller has the WordPress `update_plugins` capability and supplies an `expected_version` that exactly matches the release discovered by the updater.

Usage telemetry and error reporting are separate administrator settings and are enabled by default in this temporary pre-WordPress.org GitHub distribution phase. Either setting can be disabled under Settings > MCP Bridge.

The usage heartbeat sends only a random local installation identifier, plugin version, WordPress version, PHP major/minor version, and booleans indicating whether Divi and WooCommerce are detected. The telemetry service stores only a keyed hash of the installation identifier. Automatic error reports are restricted to fatal errors originating inside this plugin and include only sanitized plugin-owned diagnostics.

The plugin does not intentionally send the site URL or domain, administrator email, usernames or user IDs, post/page/product/order/customer content, database values, cookies, request bodies, tokens, secrets, or arbitrary plugin/theme lists. The telemetry receiver is hosted at the project Cloudflare Workers endpoint. Error records are retained for 30 days and inactive installation records for 120 days by the receiver configuration.

Before submission to WordPress.org, usage telemetry and automatic error reporting must both be changed to disabled-by-default explicit opt-in, and the privacy/readme disclosure must be reviewed against the current Plugin Directory requirements.

The project is designed to remain self-contained for its core MCP functionality. It does not require Playwright, Puppeteer, Chromium, a Node daemon, Docker, or an external SaaS service for MCP operation. The pre-WordPress.org telemetry service is optional and can be disabled.

Planned capabilities include permission-gated WordPress CRUD, semantic Divi 5 editing, optional WooCommerce operations, WordPress-rendered preview, browser-side DOM/CSS inspection, revisions, audit logging, and a separate publish gate.

Before first WordPress.org submission, replace the Contributors placeholder with the exact WordPress.org username. The temporary GitHub updater and Update URI are intended for GitHub-distributed builds and will be removed from the WordPress.org distribution.

== Installation ==

1. Install a production ZIP built from a tagged release.
2. Activate the plugin.
3. Confirm that WordPress 6.9 or newer is running.
4. Confirm that the WordPress Site Address uses HTTPS before configuring an OAuth MCP client.
5. Divi 5 and WooCommerce are detected if installed; they are not bundled.
6. GitHub release checks are enabled by default. They can be disabled under Settings > MCP Bridge.
7. Usage telemetry is enabled by default in current GitHub-distributed builds and can be disabled independently.
8. Automatic fatal-error reporting is enabled by default in current GitHub-distributed builds and can be disabled independently.

The GitHub source checkout requires Composer and is not itself the production package.

== Frequently Asked Questions ==

= Which endpoint should I use with an OAuth client such as ChatGPT? =

Use `/wp-json/mcp/mcp-oauth-server` on your HTTPS WordPress site and select OAuth authentication in the client. The client discovers the protected-resource and authorization-server metadata, opens the normal browser-based WordPress authorization flow, and then exchanges the authorization code for bearer and refresh tokens. Do not put a WordPress username, Application Password, bearer token, or other credential into the MCP server URL.

= Does OAuth replace the existing WordPress-authenticated MCP endpoint? =

No. OAuth is additive. The existing WordPress-authenticated MCP server remains available for clients that already support WordPress authentication, while OAuth-capable remote clients can use the dedicated OAuth endpoint.

= How does ChatGPT authenticate at the token endpoint? =

The authorization server advertises `none` as its token-endpoint client-authentication method. ChatGPT's CIMD metadata supports `none`, so ChatGPT uses the public-client Authorization Code + PKCE S256 exchange implemented natively by the embedded OAuth server. The authorization and token requests must carry the exact protected MCP `resource`, and the issued access token is audience-bound to that same `/wp-json/mcp/mcp-oauth-server` URL.

= Does this plugin include Divi 5 or WooCommerce? =

No. Both products are detected at runtime and remain separate dependencies.

= Does this require a headless browser or Node.js service? =

No. The target runtime is PHP + WordPress + browser-side JavaScript. Node tooling is not required at runtime.

= How are updates delivered before the WordPress.org listing is available? =

Stable GitHub Releases are used temporarily. The checker ignores GitHub prereleases, does not fall back to tags or branches, and requires the production release asset named `mcp-bridge-for-divi-woocommerce.zip`.

= Can MCP update the plugin during development? =

Yes, for this plugin only. `divi5-woocommerce-mcp/get-update-status` forces a fresh stable GitHub release check and reports current/available versions. `divi5-woocommerce-mcp/update-self` requires the WordPress `update_plugins` capability and an exact `expected_version`. It does not accept a plugin path, package URL, arbitrary source, downgrade, or prerelease from the MCP client.

= Can I disable GitHub update checks? =

Yes. Go to Settings > MCP Bridge and disable GitHub updates. The option is enabled by default. When disabled, MCP self-update is also unavailable.

= What telemetry is sent in version 0.1.9? =

A low-frequency heartbeat sends a random installation identifier, plugin version, WordPress version, PHP major/minor version, and Divi/WooCommerce detection booleans. The first heartbeat is delayed and later heartbeats are scheduled approximately weekly with jitter.

= What error information is sent? =

Only fatal errors whose source file is inside this plugin are automatically reported. Messages are redacted client-side for URLs, email addresses, absolute paths, and common secret/token patterns. Stack information is limited to plugin-owned relative paths and at most ten frames.

= Can I disable telemetry and error reporting? =

Yes. Usage telemetry and automatic error reporting are separate Settings > MCP Bridge options. Both are enabled by default only during the current pre-WordPress.org GitHub distribution phase and can be disabled independently.

= Is the plugin production ready? =

No. Version 0.1.9 is still an early development release focused on MCP foundations, OAuth interoperability, update tooling, and infrastructure for future implementation.

= Why is the license GPL-2.0-or-later? =

WordPress.org requires GPL-compatible licensing. A non-commercial restriction is not compatible with WordPress.org distribution.

== Screenshots ==

Screenshots will be added after the preview/admin UI is implemented.

== Changelog ==

= 0.1.9 =
* Fix the RFC 9728 path-inserted protected-resource metadata response so it returns HTTP 200 instead of valid JSON under a WordPress 404 status.
* Mark OAuth discovery metadata non-cacheable and use LiteSpeed Cache's public no-cache integration hook when available.
* Purge only the OAuth discovery URLs once per plugin version so stale metadata and cached 404 responses do not survive an update.
* Add regression coverage for the complete authorization-server/root/path-inserted metadata URL set.

= 0.1.8 =
* Advertise only the `none` token endpoint authentication method that the embedded OAuth server natively implements; ChatGPT supports this public-client method through CIMD negotiation.
* Remove the custom `private_key_jwt` verifier from the active token-exchange path while preserving Authorization Code + PKCE S256, CIMD, issuer binding, refresh tokens, and revocation.
* Require the exact protected MCP `resource` parameter on both `/oauth/authorize` and `/oauth/token`.
* Keep access tokens audience-bound to `/wp-json/mcp/mcp-oauth-server` and add regression coverage for resource matching and public-client metadata.

= 0.1.7 =
* Fix WordPress 6.9 MCP tool discovery when WordPress MCP Adapter 0.6.1 wires its shared ability hooks after the Abilities API initialization window.
* Ensure `mcp-adapter/discover-abilities`, `mcp-adapter/get-ability-info`, and `mcp-adapter/execute-ability` are registered early enough for `tools/list` on both MCP endpoints.
* Keep the workaround idempotent so existing or future upstream registrations are not replaced.
* Add regression coverage for the exact three-tool contract used by the OAuth server.

= 0.1.6 =
* Add experimental ChatGPT `private_key_jwt` client authentication at `/oauth/token` with RS256 signature verification against the official ChatGPT JWKS.
* Validate signed client assertions before the upstream token endpoint can consume an authorization code or refresh token.
* Require assertion issuer and subject to match the exact HTTPS ChatGPT CIMD client ID and require the audience to match this site's token endpoint.
* Continue supporting public clients using `token_endpoint_auth_method=none`.
* Advertise `private_key_jwt`, RS256 token-auth signing support, and the protected MCP resource in authorization-server metadata.
* Add the RFC 9728 path-inserted protected-resource metadata location for `/wp-json/mcp/mcp-oauth-server` while preserving the upstream root discovery document.
* Add cryptographic and discovery regression tests without introducing a new JWT dependency.

= 0.1.5 =
* Fix ChatGPT OAuth client resolution when the ChatGPT CIMD prefers `private_key_jwt` while also advertising the server-supported public-client method `none`.
* Restrict compatibility normalization to HTTPS ChatGPT CIMD URLs whose metadata is exactly self-bound and explicitly advertises `none`.
* Mark the stable `https://chatgpt.com/oauth/client.json` client ID as a verified publisher while leaving callback-specific client IDs on the validated unverified-client consent path.
* Preserve the existing PKCE, redirect validation, SSRF/DNS-rebinding protections, refresh-token rotation, and revocation.

= 0.1.4 =
* Add an OAuth 2.1 MCP endpoint at `/wp-json/mcp/mcp-oauth-server` while preserving the existing WordPress-authenticated endpoint.
* Add Authorization Code with PKCE S256, Client ID Metadata Document support, bearer access tokens, rotating refresh tokens, and revocation through the embedded OAuth layer.
* Advertise `offline_access` in OAuth authorization-server discovery for clients that use refresh-token connectivity.
* Disable OAuth when the canonical WordPress Site Address is not HTTPS.
* Keep WordPress Application Password values internal to the OAuth session implementation and out of MCP URLs and client configuration.
* Add CI validation for the reviewed OAuth dependency revision and unit coverage for HTTPS/discovery behavior.

= 0.1.3 =
* Add `divi5-woocommerce-mcp/get-update-status` to force a fresh stable GitHub release check and report current/available versions.
* Add permission-gated `divi5-woocommerce-mcp/update-self` for this plugin only.
* Require exact `expected_version` matching before installation and reject prereleases, downgrades, other plugin paths, and arbitrary package names.
* Audit update status checks and self-update outcomes without storing credentials or package secrets.
* Add unit coverage for the self-update guardrails.

= 0.1.2 =
* Add separate usage telemetry and automatic error-reporting administrator settings.
* Enable both settings by default for the temporary pre-WordPress.org GitHub distribution, with independent opt-out controls.
* Add a random local installation ID, delayed weekly heartbeat scheduling with jitter, non-blocking HTTP delivery, payload allowlists, and client-side redaction.
* Limit automatic error reports to fatal errors originating inside the plugin.
* Add WordPress privacy-policy helper content and tests covering opt-out, payload minimization, sanitization, identity generation, and scheduling.
* Document the mandatory future migration to explicit opt-in before WordPress.org submission.

= 0.1.1 =
* Update the official WordPress MCP Adapter dependency to the 0.6.x stable line.
* Add a temporary stable-only GitHub Releases updater using the production ZIP asset.
* Enable GitHub update checks by default with an administrator setting to disable them.
* Add the plugin Update URI for external distribution.
* Add unit coverage for the update setting.

= 0.1.0 =
* Initial repository and plugin bootstrap.
* Add official WordPress MCP Adapter dependency through Composer.
* Add Divi and WooCommerce runtime detection.
* Add a permission-gated, read-only integration status Ability.
* Add SemVer validation, CI, Plugin Check, production ZIP build, and guarded WordPress.org deployment workflow.
