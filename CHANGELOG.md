# Changelog

All notable changes to this project will be documented in this file.

The format follows Keep a Changelog principles and the project uses Semantic Versioning.

## [Unreleased]

### Planned

- WordPress CRUD Abilities.
- Divi 5 semantic read/write capabilities.
- Optional WooCommerce capabilities.
- Browser-based preview and DOM/CSS inspection.
- Revision-aware publish gate and persistent audit logging.

## [0.1.6] - 2026-08-30

### Added

- Standards-compliant ChatGPT `private_key_jwt` client authentication for `/oauth/token` while retaining public-client authentication with `none`.
- RS256 client-assertion verification against the fixed official ChatGPT JWKS endpoint, with exact `iss`/`sub` client binding, exact token-endpoint audience validation, bounded assertion lifetime, and clock-skew checks.
- RFC 9728 path-inserted protected-resource metadata for the path-based `/wp-json/mcp/mcp-oauth-server` resource.
- Authorization-server metadata advertising `private_key_jwt`, RS256 token-auth signing, and the exact protected MCP resource.
- Cryptographic regression tests using generated 2048-bit RSA fixtures and discovery tests covering path insertion and resource binding.

### Security

- Signed client assertions are verified before the pinned upstream token endpoint can consume a one-time authorization code or rotating refresh token.
- Unverified assertion claims are used only to constrain the request to HTTPS ChatGPT CIMD URLs and select the fixed ChatGPT JWKS; trust is established only after RS256 signature and claim validation.
- JWKS fetching uses WordPress safe HTTP requests, zero redirects, a short timeout, exact-key-id matching, and a one-hour cache with refresh on key rotation.
- RSA signing keys must be at least 2048 bits; algorithms other than RS256 are rejected.
- Public clients without a client assertion continue through the existing `none` flow without weakening PKCE, exact redirect URI validation, token rotation, revocation, or JWT audience/issuer checks.

### Changed

- Bumped the plugin version to `0.1.6`.

## [0.1.5] - 2026-08-30

### Fixed

- ChatGPT OAuth authorization no longer fails with `Unknown OAuth client.` when ChatGPT's Client ID Metadata Document prefers `private_key_jwt` but also advertises the server-supported public-client method `none`.
- Added narrowly scoped ChatGPT CIMD normalization that selects `none` only when the fetched metadata is served from an HTTPS `chatgpt.com` CIMD path, is exactly self-bound to the requested `client_id`, and explicitly includes `none` in `token_endpoint_auth_methods_supported`.
- Added the stable `https://chatgpt.com/oauth/client.json` identifier to the upstream trusted-publisher filter while leaving callback-specific ChatGPT identifiers on the existing validated unverified-client consent path.
- Added regression coverage for stable and callback-specific ChatGPT CIMD URLs, auth-method intersection, self-binding, host/path scoping, and negative cases.

### Security

- The 0.1.5 compatibility layer did not add `private_key_jwt` server support or bypass CIMD validation; the authorization server continued to advertise and accept only public-client token authentication (`none`).
- Existing upstream SSRF/DNS-rebinding protection, exact redirect-URI validation, PKCE S256, client-ID self-binding, token rotation, revocation, and WordPress-user binding remained unchanged.

### Changed

- Bumped the plugin version to `0.1.5`.

## [0.1.4] - 2026-08-30

### Added

- OAuth 2.1 authorization-code support for MCP clients through an additional `/wp-json/mcp/mcp-oauth-server` endpoint.
- PKCE S256, Client ID Metadata Document discovery, bearer access tokens, refresh-token rotation, revocation, and WordPress-user binding through the MCP OAuth layer.
- ChatGPT-oriented authorization-server discovery that advertises `offline_access` while preserving the `mcp` scope.
- CI validation that the tested `wp-media/mcp-oauth` develop dependency resolves to the reviewed revision `d6b1aa1a3b09212719b2a2e3e0979ec5e7010b93`.
- Unit coverage for HTTPS enforcement and OAuth discovery metadata.

### Security

- OAuth endpoints are disabled unless the canonical WordPress Site Address uses HTTPS.
- WordPress Application Password material remains internal to the OAuth session implementation and is never placed in MCP URLs or exposed to clients.
- The existing WordPress-authenticated MCP server remains available; OAuth is additive and does not introduce an unauthenticated MCP transport.
- Authorization-server discovery requires PKCE S256 and public-client token authentication (`none`) and exposes refresh/revocation metadata without client secrets.

### Changed

- Bumped the plugin version to `0.1.4`.
- Kept the official `wordpress/mcp-adapter` runtime at 0.6.1 while explicitly validating source compatibility with the OAuth transport integration.

## [0.1.3] - 2026-08-30

### Added

- Read-only `divi5-woocommerce-mcp/get-update-status` Ability that forces a fresh stable GitHub release check for this plugin.
- Permission-gated `divi5-woocommerce-mcp/update-self` Ability for updating only this plugin.
- Structured update results with current, available, target, installed, success, and sanitized error fields.
- Audit events for update checks and self-update outcomes.
- Unit coverage for expected-version compare-and-swap, prerelease/downgrade rejection, plugin target isolation, and exact release asset enforcement.

### Security

- Self-update accepts no plugin path, package URL, source repository, or arbitrary installer input from MCP clients.
- Installation requires the WordPress `update_plugins` capability and an `expected_version` that exactly matches the freshly detected release.
- Candidate validation rejects prerelease versions, downgrades, other plugin basenames, and packages whose filename is not exactly `mcp-bridge-for-divi-woocommerce.zip`.

### Changed

- Bumped the plugin version to `0.1.3`.

## [0.1.2] - 2026-08-30

### Added

- Separate Settings > MCP Bridge controls for usage telemetry and automatic error reporting.
- Random local installation identifier used only for telemetry pseudonymization.
- Delayed first heartbeat and approximately weekly one-shot heartbeat scheduling with bounded jitter.
- Non-blocking telemetry HTTP client with short timeout, zero redirects, and strict per-endpoint payload allowlists.
- Client-side redaction for URLs, email addresses, absolute paths, query fragments, and common secret/token patterns.
- Automatic fatal-error reporting restricted to errors originating inside this plugin, with plugin-relative stack information only.
- WordPress privacy-policy helper disclosure for the current telemetry behavior.
- Unit coverage for opt-out behavior, payload minimization, installation identity generation, sanitization, plugin-file ownership, and scheduling bounds.

### Changed

- Bumped the plugin version to `0.1.2`.
- For the temporary pre-WordPress.org GitHub distribution, usage telemetry and automatic error reporting are enabled by default with independent administrator opt-out controls.
- Documented the mandatory migration of both telemetry controls to disabled-by-default explicit opt-in before WordPress.org submission.

## [0.1.1] - 2026-08-29

### Added

- Temporary GitHub Releases update channel for pre-WordPress.org distribution.
- Administrator setting under Settings > MCP Bridge to disable GitHub update checks.
- `Update URI` plugin header for the external distribution channel.
- Unit coverage for update-setting normalization.

### Changed

- Updated `wordpress/mcp-adapter` dependency from `^0.5.0` to `^0.6.1`.
- Added `yahnis-elsts/plugin-update-checker:^5.7`.
- Restricted update discovery to stable GitHub Releases only, with no tag or branch fallback.
- Required the exact production asset `mcp-bridge-for-divi-woocommerce.zip`; source-archive fallback is disabled.
- GitHub update checks are enabled by default; GitHub prereleases are not offered as updates.
- Plugin Check continues to run repository/general/security checks while temporarily excluding only the external updater check.
- WordPress.org deployment now hard-fails until the GitHub updater, external Update URI, and updater dependency have been removed.

## [0.1.0] - 2026-08-29

### Added

- Initial WordPress plugin bootstrap.
- WordPress 6.9+ Abilities API integration.
- Official WordPress MCP Adapter Composer dependency.
- Jetpack Autoloader dependency.
- Runtime Divi and WooCommerce detection.
- Read-only integration status Ability.
- SemVer consistency check.
- CI, Plugin Check, distributable ZIP build, and guarded WordPress.org deployment workflow.
- GPL-2.0-or-later licensing and project governance documentation.
