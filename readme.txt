=== MCP Bridge for Divi 5 and WooCommerce ===
Contributors: TODO-wordpress-org-username
Tags: mcp, divi, woocommerce, ai, automation
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 1.2.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure MCP access for WordPress with runtime-driven Divi 5 authoring, OAuth, reproducible dependencies, server-side render inspection, and optional real-pixel screenshots.

== Description ==

MCP Bridge for Divi 5 and WooCommerce builds on the WordPress Abilities API and the official WordPress MCP Adapter.

Version 1.2.0 adds a read-only `divi-screenshot` ability for real frontend pixels at arbitrary viewport widths. The plugin does not reconstruct screenshots from server-side HTML, DOM parsing, GD, Imagick, or PDF layout and does not bundle Playwright, Puppeteer, Node.js, Chromium, JavaScript browser automation, or a screenshot SaaS. A successful capture requires a hosting-specific renderer implementing `ScreenshotEngineInterface`; otherwise the ability returns `render_engine_unavailable`.

Screenshot targets are generated from a WordPress `post_id`; callers cannot supply arbitrary URLs. Targets must remain on the current site's host/port. Non-published previews use short-lived HMAC authorization bound to the post, user, exact target, and expiry. Returned PNG/JPEG bytes, dimensions, format, width, total pixels, byte size, and renderer limits are validated before MCP transport.

Version 1.2.0 also makes Composer installation reproducible. `composer.lock` is the authoritative dependency graph used by CI and release builds. The OAuth integrity check derives the installed `wp-media/mcp-oauth` revision from the lock, validates trusted upstream provenance, and rejects an installed revision that differs from the lock. CI additionally runs `composer audit --locked` and GitHub Dependency Review.

Version 1.1.1 corrected the generic Divi Custom Attribute writer. Wrapper class/id remain in Divi's Advanced HTML Attributes structure, while other safe module-wrapper attributes are stored as native `{name,value,targetElement}` records under `module.decoration.attributes.<breakpoint>.value.attributes`. Responsive writes require exact runtime-discovered persisted paths and state writes fail closed unless the runtime exposes an explicit native state path.

Version 1.1.0 extended the clean-break runtime/document foundation with generic runtime registry discovery, snapshot-bound native validation/mutation, module presets when runtime evidence proves the mapping, semantic Divi Custom Attributes, and server-side render inspection.

Version 1.0.0 introduced the clean-break runtime/document foundation as the primary Divi API: runtime discovery, module introspection, normalized document snapshots, dry-run validation, atomic mutation, and optimistic concurrency through `document_token`.

Normal Divi write abilities remain restricted to draft, pending, or auto-draft content. Complete mutation batches are validated before persistence. Arbitrary nested native paths, guessed responsive/state mappings, unsafe event-handler attributes, generic filesystem writes, arbitrary SQL/PHP execution, and unrestricted publishing are not exposed.

The OAuth MCP endpoint is `/wp-json/mcp/mcp-oauth-server`. It uses Authorization Code with PKCE S256, bearer access tokens, rotating refresh tokens, revocation, issuer/resource binding, and protected-resource discovery. OAuth is enabled only when the canonical WordPress Site Address uses HTTPS.

Divi 5 and WooCommerce remain separate products and are detected at runtime; they are not bundled.

== MCP Abilities ==

The plugin exposes bridge abilities through the WordPress MCP Adapter gateway, including:

* `divi5-woocommerce-mcp/get-status` - plugin, Divi, native authoring, and WooCommerce status.
* `divi5-woocommerce-mcp/get-update-status` - fresh stable GitHub release check.
* `divi5-woocommerce-mcp/update-self` - permission-gated update of only this plugin to an exact discovered stable version.
* `divi5-woocommerce-mcp/divi-runtime-describe` - describe the clean-break API, active runtime, providers, fingerprints, and capability states.
* `divi5-woocommerce-mcp/divi-runtime-list-registries` - list runtime-derived registries and support/unknown evidence.
* `divi5-woocommerce-mcp/divi-runtime-describe-registry` - inspect one discovered runtime registry.
* `divi5-woocommerce-mcp/divi-module-describe` - inspect one runtime-discovered module and its normalized parameter graph.
* `divi5-woocommerce-mcp/divi-document-get` - read a normalized Divi document snapshot and exact SHA-256 `document_token`.
* `divi5-woocommerce-mcp/divi-document-validate` - dry-run an atomic semantic mutation batch.
* `divi5-woocommerce-mcp/divi-document-mutate` - persist a validated semantic batch to editable draft/pending content.
* `divi5-woocommerce-mcp/divi-document-native-validate` - dry-run runtime-proven native set/unset, responsive/state, preset, and Custom Attribute operations.
* `divi5-woocommerce-mcp/divi-document-native-mutate` - persist the same native operations after complete validation.
* `divi5-woocommerce-mcp/divi-render` - server-side render and inspect HTML metadata, classes, IDs, inline CSS, warnings, and basic selectors.
* `divi5-woocommerce-mcp/divi-screenshot` - obtain real PNG/JPEG frontend pixels through a compatible hosting-supplied renderer.

The older v0.4 layout/module abilities remain available as compatibility shims. New integrations should prefer the clean-break runtime/document API.

== Installation ==

1. Install a production ZIP built from a tagged GitHub release.
2. Activate the plugin.
3. Confirm WordPress 6.9 or newer is running.
4. Confirm the WordPress Site Address uses HTTPS before configuring OAuth MCP clients.
5. Install and activate Divi 5 to use Divi runtime/authoring abilities.
6. WooCommerce is optional.
7. Connect the MCP client to `/wp-json/mcp/mcp-oauth-server` and use OAuth authentication.

The GitHub source checkout requires Composer and is not itself the production package.

== Frequently Asked Questions ==

= What is the primary Divi API in version 1.2.0? =

Use the clean-break runtime/document abilities plus the generic runtime bridge: runtime/registry discovery, module describe, document get, semantic/native validate and mutate, and `divi-render`. `divi-runtime-describe` negotiates capability status from the active runtime. The older path-oriented abilities remain compatibility shims.

= Does divi-screenshot always work? =

No. It succeeds only when the hosting environment registers a compatible real-pixel renderer through `divi5_woocommerce_mcp_screenshot_engine`. Without one, the truthful result is `render_engine_unavailable`; the plugin does not fall back to an approximate HTML/GD/Imagick screenshot.

= Does the plugin bundle a browser or send pages to a screenshot SaaS? =

No. It bundles no Playwright, Puppeteer, Node.js, Chromium, browser daemon, or screenshot SaaS. A renderer is a separate hosting/integration concern and receives only a same-site URL generated and validated by the plugin.

= Can divi-screenshot capture draft/private content? =

For non-published content the plugin creates the normal WordPress preview target plus short-lived HMAC authorization tied to the exact post, current MCP user, target, and expiry. The user must still have `edit_post` permission. The temporary authorization target is not returned as the public `page_url` result.

= Does divi-render provide browser computed styles? =

No. `divi-render` is server-side WordPress/Divi rendering. Browser layout, computed CSS cascades, dimensions, and interactive state execution require a real visual renderer or other browser-capable integration.

= Does raw-native write mean arbitrary Divi JSON can be written? =

No. Top-level attributes must exist in the registered WordPress block schema. Nested locations must be proven by runtime metadata or a narrowly defined Divi adapter contract. Writes require a current document token and editable draft/pending content.

= How are Composer dependencies protected from silent changes? =

`composer.lock` is committed and authoritative. CI and releases use `composer install`. The Dependency Integrity job validates the lock, installs it, verifies OAuth provenance/reference, runs `composer audit --locked`, and confirms installation did not modify the lock. Pull requests also run GitHub Dependency Review for high-severity dependency changes.

= Which MCP endpoint should OAuth clients use? =

Use `/wp-json/mcp/mcp-oauth-server` on the HTTPS WordPress site. Do not place WordPress usernames, Application Passwords, bearer tokens, refresh tokens, or authorization codes into the MCP server URL.

= Can these abilities overwrite a published page directly? =

No. Divi editing is restricted to draft, pending, or auto-draft content. Publishing remains a separate controlled action.

= Is this a complete Visual Builder replacement? =

No. Version 1.2.0 materially expands conservative draft-first runtime authoring and optional visual verification, but complete Design Variables/relative-color CRUD, authoritative global state/preset/provider registries, Theme Builder/global systems, publishing workflows, and browser computed-style inspection remain future or runtime-dependent work.

== Screenshots ==

The MCP `divi-screenshot` ability returns real image content to compatible MCP clients when the hosting environment supplies a real-pixel renderer. This section is not a WordPress.org marketing screenshot gallery.

== Changelog ==

= 1.2.0 =
* Add the read-only `divi-screenshot` MCP ability and hosting-supplied `ScreenshotEngineInterface` for real frontend pixel rendering.
* Keep server-side `divi-render` separate from real raster capture and fail explicitly with `render_engine_unavailable` when no compatible engine exists.
* Restrict screenshot targets to WordPress-generated same-site URLs, add short-lived signed preview authorization, and validate returned image bytes/dimensions/limits.
* Add `capabilities.screenshot` and bump the negotiated clean-break descriptor API to `1.2.0-alpha.1`.
* Commit `composer.lock` as the authoritative dependency graph and make normal CI/release installation deterministic.
* Replace the disconnected OAuth hardcoded SHA check with lock-derived trusted-repository/reference verification.
* Add `Dependency Integrity`, `composer audit --locked`, lock immutability verification, and GitHub Dependency Review.

= 1.1.1 =
* Fix generic Divi Custom Attributes to use native `module.decoration.attributes.<breakpoint>.value.attributes` record lists.
* Preserve unrelated records while merging/updating/removing by `(name,targetElement)`.
* Require exact runtime-discovered responsive paths and explicit runtime-proven state paths.

= 1.1.0 =
* Add generic runtime registry discovery and capability fingerprints.
* Add snapshot-bound generic native validation/mutation for runtime-proven paths, responsive/state values, presets, and semantic Custom Attributes.
* Add server-side Divi render and basic markup inspection.

= 1.0.0 =
* Add the clean-break runtime/document API with runtime/module describe, document get, validate, and mutate abilities.
* Add normalized snapshot handles and stale-token rejection for atomic mutation batches.
* Discover compatible Divi modules from the active runtime without a static vendor catalog.

For older release history see `CHANGELOG.md` in the GitHub repository.
