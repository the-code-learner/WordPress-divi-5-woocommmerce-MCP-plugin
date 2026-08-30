=== MCP Bridge for Divi 5 and WooCommerce ===
Contributors: TODO-wordpress-org-username
Tags: mcp, divi, woocommerce, ai, automation
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 0.3.1
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure MCP access for WordPress with native Divi 5 authoring, OAuth, controlled updates, and foundations for WooCommerce workflows.

== Description ==

MCP Bridge for Divi 5 and WooCommerce builds on the WordPress Abilities API and the official WordPress MCP Adapter.

Version 0.3.1 fixes the parameterless Divi module catalog so WordPress can pass its native `null` input value through the ability permission and execution callbacks.

Version 0.3.0 adds runtime discovery for every native Divi module registered on the site, including per-module block schemas and Divi default attributes. Draft layouts can now be edited structurally by inserting constrained semantic modules, deleting modules, moving or reordering modules, and deep-duplicating modules with their nested children and design attributes. Every operation uses paths from live layout inspection, validates the destination hierarchy, requests a revision, and preserves the native Divi block format.

Version 0.2.2 preserves the plugin's site or network activation state during MCP self-updates. The updater now follows WordPress's bulk update path, restores the original activation scope if an update attempt changes it, and reports a failure instead of returning success when reactivation cannot be verified.

Version 0.2.1 fixes native authoring on Divi runtimes where the official converter is available but returns the backward-compatible `divi/shortcode-module` wrapper because core conversion outlines are not loaded in the MCP request. The writer still uses `Conversion::maybeConvertContent()` first, rejects shortcode fallbacks, and then uses a constrained native block serializer for the verified Section, Row, Column, Text, Button, Image, Code, and Divider schema.

Version 0.2.0 added the first native Divi 5 authoring surface. When Divi 5 is available, authenticated MCP clients can inspect a page's native Divi block tree, replace draft content from a constrained semantic layout, and patch the attribute object of an existing native Divi block.

The initial semantic layout vocabulary supports sections, rows, columns, Text, Button, Image, Code, and Divider modules. The lower-level module patch ability can update the native Divi 5 attribute object returned by layout inspection, enabling design settings such as typography, spacing, sizing, backgrounds, responsive values, links, presets, and other attributes supported by the installed Divi version.

Normal Divi write abilities are intentionally restricted to draft, pending, or auto-draft content. A revision is requested before each write, `_et_pb_use_builder` is kept enabled, and the existing publish gate remains separate from editing.

Version 0.2.0 also fixes MCP exposure metadata for the bridge's own abilities. The WordPress MCP Adapter requires `meta.mcp.public=true`; the plugin now sets that flag explicitly while keeping direct REST exposure disabled.

The OAuth MCP endpoint is `/wp-json/mcp/mcp-oauth-server`. It uses Authorization Code with PKCE S256, ChatGPT Client ID Metadata Document compatibility, bearer access tokens, rotating refresh tokens, revocation, issuer/resource binding, and RFC 9728 protected-resource discovery. OAuth is enabled only when the canonical WordPress Site Address uses HTTPS.

WordPress Application Password values remain internal to the OAuth implementation and are never intended to be placed in MCP URLs, client configuration, logs, or project memory.

The plugin remains self-contained for its runtime MCP functionality. Divi 5 and WooCommerce are detected integrations and are not bundled. No Playwright, Puppeteer, Chromium, Node daemon, Docker service, arbitrary SQL console, arbitrary PHP execution, or generic filesystem write surface is required or exposed.

Usage telemetry and automatic fatal-error reporting are separate administrator settings during the temporary pre-WordPress.org GitHub distribution phase and can be disabled independently under Settings > MCP Bridge. Before WordPress.org submission these controls must be reviewed and changed to explicit opt-in as required by the distribution policy.

== MCP Abilities ==

The plugin exposes these bridge abilities through the WordPress MCP Adapter gateway:

* `divi5-woocommerce-mcp/get-status` - plugin, Divi, native Divi authoring, and WooCommerce status.
* `divi5-woocommerce-mcp/get-update-status` - fresh stable GitHub release check.
* `divi5-woocommerce-mcp/update-self` - update only this plugin to an exact discovered stable version.
* `divi5-woocommerce-mcp/divi-get-layout` - inspect the native Divi 5 block tree and block paths.
* `divi5-woocommerce-mcp/divi-save-layout` - replace draft content with a semantic layout saved as validated native Divi 5 blocks.
* `divi5-woocommerce-mcp/divi-update-module` - patch one native Divi 5 block's attributes by the path returned from `divi-get-layout`.
* `divi5-woocommerce-mcp/divi-list-modules` - list native Divi modules registered by the active runtime.
* `divi5-woocommerce-mcp/divi-get-module-schema` - inspect one registered module's attributes, defaults, supports, and nesting constraints.
* `divi5-woocommerce-mcp/divi-insert-module` - insert a constrained semantic module at a validated parent path and child index.
* `divi5-woocommerce-mcp/divi-delete-module` - delete one native module without permitting removal of the root or last usable layout.
* `divi5-woocommerce-mcp/divi-move-module` - move or reorder a native module with final-index semantics.
* `divi5-woocommerce-mcp/divi-duplicate-module` - deep-copy a module, its children, and its native design attributes.

== Installation ==

1. Install a production ZIP built from a tagged GitHub release.
2. Activate the plugin.
3. Confirm WordPress 6.9 or newer is running.
4. Confirm the WordPress Site Address uses HTTPS before configuring OAuth MCP clients.
5. Install and activate Divi 5 to use native Divi authoring abilities.
6. WooCommerce is optional and remains a separate dependency.
7. Connect the MCP client to `/wp-json/mcp/mcp-oauth-server` and use OAuth authentication.

The GitHub source checkout requires Composer and is not itself the production package.

== Frequently Asked Questions ==

= Are pages created with the Divi abilities editable in the Visual Builder? =

Yes. `divi-save-layout` uses Divi's own conversion layer first and rejects `divi/shortcode-module` fallbacks. If the installed Divi runtime has not loaded its core conversion outlines in the MCP request, the plugin serializes the same constrained vocabulary directly into native `wp:divi/*` blocks.

= Can MCP edit an already existing native Divi 5 module? =

Yes. Call `divi-get-layout` first, locate the module path and current attribute object, then call `divi-update-module` with a recursive attribute patch. This low-level write requires permission to edit the post and the `unfiltered_html` capability because Divi attribute objects can contain rich content and advanced settings.

= Can these abilities overwrite a published page directly? =

No. Divi editing is currently restricted to draft, pending, or auto-draft content. Publishing remains a separate controlled action.

= Does the plugin include Divi 5 or WooCommerce? =

No. Both remain separate products and are detected at runtime.

= Which MCP endpoint should OAuth clients use? =

Use `/wp-json/mcp/mcp-oauth-server` on the HTTPS WordPress site. Do not put WordPress usernames, Application Passwords, bearer tokens, refresh tokens, or authorization codes into the MCP server URL.

= How are updates delivered before WordPress.org? =

Stable GitHub Releases are the temporary distribution channel. The updater ignores prereleases and expects the production asset `mcp-bridge-for-divi-woocommerce.zip`.

= Can MCP update this plugin? =

Yes. `divi5-woocommerce-mcp/update-self` can update only this plugin, requires the `update_plugins` capability, and requires an exact `expected_version` matching the stable release discovered by the updater.

= Is this plugin production ready? =

No. Version 0.3.1 is an early development release. Native Divi editing is intentionally conservative and draft-first while the supported semantic module surface expands.

== Screenshots ==

Screenshots will be added after the preview/admin UI is implemented.

== Changelog ==

= 0.3.1 =
* Accept WordPress's native null input for the parameterless `divi-list-modules` ability callbacks.
* Add regression coverage for parameterless module discovery through the Abilities API.

= 0.3.0 =
* Discover the complete native Divi module registry and inspect per-module runtime schemas and defaults.
* Insert constrained semantic modules into existing native Divi layouts.
* Delete, move, reorder, and deep-duplicate inspected native modules with validated hierarchy rules.
* Preserve revisions, draft-only writes, native block serialization, and Visual Builder state across structural edits.
* Add regression coverage for block tree operations, hierarchy validation, schemas, and public MCP exposure.

= 0.2.2 =
* Preserve site and multisite network activation across MCP self-update attempts.
* Use WordPress's bulk plugin update path so an active plugin is not deliberately deactivated during a non-browser MCP request.
* Reject false update success when the original activation state cannot be restored and verified.
* Add regression coverage for site activation, network activation, inactive plugins, activation errors, and false-success prevention.

= 0.2.1 =
* Reject `divi/shortcode-module` as a successful native conversion or editable native module.
* Fall back to constrained native Divi 5 block serialization when the official converter has not loaded core conversion outlines in the MCP request.
* Count only semantic Divi 5 modules during layout inspection.
* Add regression coverage for the verified native Section/Row/Column/Text/Button schema and fallback rejection.

= 0.2.0 =
* Add native Divi 5 layout inspection through `divi-get-layout`.
* Add semantic draft layout authoring through Divi 5's official conversion API with Section, Row, Column, Text, Button, Image, Code, and Divider support.
* Add native Divi 5 block attribute patching through `divi-update-module`.
* Restrict Divi writes to draft/pending content and request a revision before updates.
* Keep `_et_pb_use_builder` enabled for generated/edited content.
* Explicitly expose bridge abilities to the MCP Adapter with `meta.mcp.public=true` while keeping REST exposure disabled.
* Add regression coverage for semantic layout hierarchy/escaping and MCP exposure metadata.

= 0.1.9 =
* Fix RFC 9728 protected-resource metadata responses so valid discovery JSON returns HTTP 200 instead of a WordPress 404 status.
* Mark OAuth discovery metadata non-cacheable and purge stale discovery URLs once per plugin version.

= 0.1.8 =
* Align ChatGPT token exchange with the upstream public-client `none` + PKCE flow and require the exact protected MCP resource.

= 0.1.7 =
* Ensure the MCP Adapter's three shared gateway abilities are registered in time on WordPress 6.9.

= 0.1.6 =
* Add the RFC 9728 path-inserted protected-resource metadata location and experimental signed-client compatibility work later superseded by 0.1.8.

= 0.1.5 =
* Fix ChatGPT CIMD client resolution while preserving strict HTTPS, self-binding, and supported-auth-method checks.

= 0.1.4 =
* Add the OAuth MCP endpoint with Authorization Code, PKCE S256, CIMD, bearer tokens, rotating refresh tokens, and revocation.

= 0.1.3 =
* Add stable GitHub release status and permission-gated self-update abilities.

= 0.1.2 =
* Add configurable telemetry and plugin-owned fatal-error reporting for the development distribution phase.

= 0.1.1 =
* Update the WordPress MCP Adapter dependency and add the temporary stable GitHub Releases updater.

= 0.1.0 =
* Initial plugin bootstrap with WordPress Abilities API, MCP Adapter integration, Divi/WooCommerce detection, CI, and production ZIP build.
