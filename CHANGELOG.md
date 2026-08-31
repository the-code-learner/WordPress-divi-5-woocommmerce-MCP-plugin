# Changelog

All notable changes to this project will be documented in this file.

The format follows Keep a Changelog principles and the project uses Semantic Versioning.

## [Unreleased]

### Planned

- WordPress CRUD Abilities.
- Optional WooCommerce capabilities.
- Browser-based preview and DOM/CSS inspection.
- Runtime-proven state/preset write mappings.
- Revision-aware publish gate and persistent audit logging.

## [1.1.0] - 2026-08-31

### Added

- Generic Divi runtime registry discovery for modules, providers, field components, option groups, breakpoints, states, dynamic-content capability, attribute roots, and layout engines, with schema/feature fingerprints and explicit unknown states.
- Runtime-proven native document validation and mutation abilities for safe native set/unset operations, responsive/state values, semantic module-wrapper Custom Attributes, and module preset assignment when runtime evidence proves the mapping.
- Server-side Divi rendering and lightweight selector inspection with generated classes, IDs, inline CSS, markup warnings, and explicit reporting that browser computed styles remain unavailable.

### Security

- Native writes require editable draft/pending content and a fresh document snapshot token; stale tokens are rejected.
- Arbitrary nested native paths are rejected unless runtime metadata or an explicit Divi adapter contract proves them.
- Event-handler Custom Attributes such as `onclick` are rejected, and complete native mutation batches are validated before persistence.
- Unknown registries, state mappings, preset IDs, and other runtime systems remain unknown instead of being synthesized or hard-coded.

### Changed

- Negotiated runtime descriptor API is now `1.1.0-alpha.1` and reports registry discovery, raw-native writing, state editing, preset application, rendering, and partial inspection capabilities conservatively from the active Divi runtime.
- Bumped plugin, package, and WordPress stable release metadata to `1.1.0`.

### Tests

- Added regression coverage for semantic Custom Attribute class mapping, safe generic HTML attributes, event-handler rejection, runtime-proven responsive breakpoints including non-hardcoded names, hover state mapping, preset assignment, stale/unproven native paths, and render inspection.

## [1.0.0] - 2026-08-31

### Added

- Clean-break Divi runtime/document API with `divi-runtime-describe`, `divi-module-describe`, `divi-document-get`, `divi-document-validate`, and `divi-document-mutate`.
- Normalized Divi document AST with deterministic snapshot-scoped handles, numeric paths retained only as locators, and SHA-256 document tokens for optimistic concurrency.
- Runtime-driven discovery of core and compatible third-party Divi modules without static vendor catalogs.
- Full module parameter introspection plus a narrower mutation-facing parameter set generated only from runtime-proven persisted value mappings.
- Normalization of Divi `desktop`/`tablet`/`phone` value envelopes and runtime capability reporting with explicit `supported`, `unknown`, and `unavailable` states.
- Atomic semantic mutation planning for insert, set, delete, move, duplicate, and runtime-proven responsive operations.
- Machine-readable validation errors identifying the operation, node, property, offending value, and expected contract.

### Security

- Semantic/UI paths remain distinct from persisted native paths; native storage paths are never synthesized mechanically from group lineage and `subName`.
- A parameter is exposed for mutation only when both its persisted path and a validator-compatible value contract are demonstrated by runtime evidence.
- Unknown or opaque value contracts remain introspection-only even when a possible persisted path is observable.
- Mutation rejects stale document tokens, validates the complete batch before persistence, and performs one post-content write only after all operations pass.
- Normal writes remain restricted to editable `draft`, `pending`, and `auto-draft` posts; publishing remains a separate capability.
- Raw-native mutation, unproven state/preset writes, render, and DOM/computed-style inspection remain unavailable rather than falling back to guessed behavior.

### Changed

- Bumped the plugin version from `0.4.0` to `1.0.0` for the clean-break next-major architecture milestone.
- Retained the v0.4.0 Divi abilities as compatibility shims while making the runtime/document model the primary API direction.
- Updated document authoring metadata and public documentation to reflect implemented validate/mutate support without overclaiming unfinished render/inspect/state/preset capabilities.

### Tests

- Added regression coverage for runtime module discovery, third-party namespace handling, snapshot handle stability, normalized document properties, atomic batch semantics, responsive writes, unsupported state/preset mappings, and clean-break ability registration.
- Added live-schema-derived regression fixtures for Text, Button, Section, Column, and Image, including the `forceFullwidth` case where the UI/group path and persisted storage path differ.
- Added a value-contract gate regression proving that a runtime-proven path with an opaque vendor type remains non-authorable.

## [0.4.0] - 2026-08-30

### Added

- Low-level `divi-insert-native-module` MCP authoring for any native `divi/*` module registered by the active Divi 5 runtime.
- Recursive native module trees for verified relationships such as Accordion/Accordion Item, Tabs/Tab, Slider/Slide, Contact Form/Contact Field, Pricing Tables/Pricing Table, and other relationships exposed by the installed runtime.
- Exact runtime-native attribute preservation so module-specific design, responsive, preset, variable, and advanced settings can be authored from live schemas without hard-coded semantic translation.

### Security

- Native module insertion remains restricted to editable draft, pending, or auto-draft content and requires both `edit_post` and `unfiltered_html`.
- Every inserted node must be a registered semantic `divi/*` block, unknown node properties are rejected, and every parent/child relationship is validated before serialization or persistence.

### Changed

- Bumped the plugin version to `0.4.0`.
- Kept the existing constrained `divi-insert-module` ability unchanged for backward-compatible semantic authoring.

### Tests

- Added regression coverage for recursive native module serialization, invalid nested relationships, non-Divi block rejection, unknown node property rejection, and MCP ability registration metadata.

## [0.3.2] - 2026-08-30

### Fixed

- Register the Divi module catalog without an input schema so the WordPress Abilities API treats it as a native parameterless ability.
- Use zero-argument execution and permission callbacks, matching the plugin's established status ability contract.

### Changed

- Bumped the plugin version to `0.3.2`.

### Tests

- Assert that the catalog registration omits `input_schema` and executes without arguments.

## [0.3.1] - 2026-08-30

### Fixed

- Accept the WordPress Abilities API's native `null` input for the parameterless Divi module catalog permission and execution callbacks.

### Changed

- Bumped the plugin version to `0.3.1`.

### Tests

- Added regression coverage that executes the module catalog callback with a null ability input.

## [0.3.0] - 2026-08-30

### Added

- Runtime discovery of registered native Divi modules and detailed per-module schemas, supports, defaults, and nesting constraints.
- Native structural abilities to insert constrained semantic modules and delete, move, reorder, or deep-duplicate inspected modules.
- A block-tree editor that preserves WordPress child serialization slots while rejecting invalid paths, indexes, self-descendant moves, and unsupported parent/child relationships.

### Security

- Structural writes remain restricted to editable draft, pending, or auto-draft content and never accept arbitrary block names.
- Cross-container edits are validated against core Section/Row/Column rules, registered block constraints, and verified Divi nested-module relationships.

### Changed

- Bumped the plugin version to `0.3.0`.

### Tests

- Added regression coverage for structural tree mutations, hierarchy gates, single-node native serialization, ability schemas, and MCP exposure.

## [0.2.2] - 2026-08-30

### Fixed

- Preserve the plugin's original site or multisite network activation state across MCP self-update attempts.
- Use WordPress's bulk update path so a non-browser request does not deliberately leave the plugin inactive.
- Treat an unverified activation restoration as an update failure instead of returning a false success.

### Changed

- Bumped the plugin version to `0.2.2`.

### Tests

- Added regression coverage for site activation, network activation, inactive plugins, activation errors, and false-success prevention.

## [0.1.8] - 2026-08-30

### Fixed

- Restored ChatGPT's token-exchange path to the upstream OAuth server's native public-client flow by advertising only `token_endpoint_auth_methods_supported: ["none"]`; ChatGPT supports this method through the intersection of its CIMD metadata with the authorization-server metadata.
- Removed the custom `private_key_jwt` verifier from the active OAuth request path so a JWKS fetch or client-assertion validation cannot block an otherwise valid Authorization Code + PKCE exchange.
- Added exact MCP `resource` validation on both `/oauth/authorize` and `/oauth/token`, matching the protected-resource metadata URL that the upstream token issuer already places in the access-token `aud` claim.
- Added regression coverage for public-client metadata and exact protected-resource matching.

### Security

- PKCE S256, exact redirect-URI validation, CIMD self-binding, issuer identification, refresh-token rotation, revocation, WordPress-user binding, and bearer-token audience validation remain in place.
- Resource indicators are now rejected when missing or different from the canonical `/wp-json/mcp/mcp-oauth-server` protected resource.
- The dormant `private_key_jwt` implementation remains in the codebase for review/reference but is neither advertised nor registered in the runtime request path.

### Changed

- Bumped the plugin version to `0.1.8`.

## [0.1.7] - 2026-08-30

### Fixed

- Restored MCP `tools/list` discovery on WordPress 6.9 when the pinned `wordpress/mcp-adapter` 0.6.1 adds its default shared-ability hooks after the one-shot Abilities API registration window has already fired.
- Added an idempotent compatibility layer that ensures `mcp-adapter/discover-abilities`, `mcp-adapter/get-ability-info`, and `mcp-adapter/execute-ability` are registered during `wp_abilities_api_init` before either MCP server is created.
- Preserved existing upstream registrations when present so the compatibility layer can coexist safely with a future upstream timing fix.
- Added regression coverage for the exact shared-tool contract consumed by the OAuth MCP server.

### Changed

- Bumped the plugin version to `0.1.7`.

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
