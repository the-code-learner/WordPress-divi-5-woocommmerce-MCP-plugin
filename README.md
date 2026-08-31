# MCP Bridge for Divi 5 and WooCommerce

A WordPress plugin that exposes controlled WordPress and Divi 5 capabilities to MCP clients through the WordPress Abilities API and the official `wordpress/mcp-adapter` bridge.

The primary Divi surface is the **clean-break Runtime + Document API**: runtime-driven discovery, normalized document reads, dry-run validation, atomic semantic/native mutation, server-side render inspection, and an optional real-pixel screenshot ability.

> **Plugin version:** `1.2.0`  
> **Clean-break API generation:** `clean-break-1`  
> **Clean-break API version:** `1.2.0-alpha.1`  
> **Primary API:** `clean-break-runtime-document+generic-runtime-bridge`

## Requirements

- WordPress **6.9+** because the plugin uses the core Abilities API.
- PHP **7.4+**.
- Divi 5 for Divi runtime discovery and native authoring. Divi is detected at runtime and is not bundled.
- WooCommerce is optional and detected at runtime; it is not bundled.
- Composer is required for development builds. Production release ZIPs include the required PHP dependencies.

## Installation

Install the production asset from a tagged GitHub Release:

```text
mcp-bridge-for-divi-woocommerce.zip
```

Upload it through **Plugins > Add New > Upload Plugin** in WordPress, install, and activate it.

For a development checkout:

```bash
composer install
```

A source checkout is not the distributable package. Production builds are created by `scripts/build-zip.sh` after installing the locked production dependencies.

## MCP endpoint and OAuth

The OAuth MCP endpoint is:

```text
/wp-json/mcp/mcp-oauth-server
```

OAuth uses Authorization Code with PKCE S256, bearer access tokens, rotating refresh tokens, revocation, issuer/resource binding, and protected-resource discovery. OAuth is enabled only when the canonical WordPress Site Address uses HTTPS.

WordPress usernames, Application Passwords, bearer tokens, refresh tokens, authorization codes, and other credentials must not be embedded in MCP server URLs.

## Current abilities

### Status and updates

- `divi5-woocommerce-mcp/get-status`
- `divi5-woocommerce-mcp/get-update-status`
- `divi5-woocommerce-mcp/update-self`

The self-update path is restricted to this plugin and the stable GitHub release channel. `update-self` requires the WordPress `update_plugins` capability and an exact expected version.

### Runtime and registry discovery

- `divi5-woocommerce-mcp/divi-runtime-describe`
- `divi5-woocommerce-mcp/divi-runtime-list-registries`
- `divi5-woocommerce-mcp/divi-runtime-describe-registry`
- `divi5-woocommerce-mcp/divi-module-describe`

Runtime capabilities are derived from the active Divi installation. Unknown systems remain `unknown`; the plugin does not infer support merely from product or field names.

### Snapshot-bound document authoring

- `divi5-woocommerce-mcp/divi-document-get`
- `divi5-woocommerce-mcp/divi-document-validate`
- `divi5-woocommerce-mcp/divi-document-mutate`
- `divi5-woocommerce-mcp/divi-document-native-validate`
- `divi5-woocommerce-mcp/divi-document-native-mutate`

Document reads return a SHA-256 `document_token` for optimistic concurrency. Validation and mutation are bound to that exact snapshot. Stale tokens are rejected instead of applying a plan to changed content.

Writes are intentionally conservative:

- normal Divi mutation is limited to `draft`, `pending`, or `auto-draft` content;
- complete batches are validated before one persistence operation;
- arbitrary nested native paths are rejected unless runtime metadata or a narrow Divi adapter contract proves the location;
- responsive writes require an exact runtime-discovered persisted path for the requested breakpoint;
- state writes require an explicit runtime-proven native state path;
- unsafe event-handler Custom Attributes are rejected;
- wrapper class/id and safe Custom Attributes use verified Divi 5 native storage.

### Server-side render inspection

- `divi5-woocommerce-mcp/divi-render`

`divi-render` executes WordPress/Divi server-side block rendering and can report markup, classes, IDs, inline CSS, warnings, and basic selector matches. It does **not** claim to provide browser layout, computed-style cascades, dimensions, or interactive-state execution.

### Real-pixel screenshot ability

- `divi5-woocommerce-mcp/divi-screenshot`

Version 1.2.0 adds a read-only visual capture contract for real frontend pixels at arbitrary viewport widths from 240 through 4096 px.

The plugin deliberately does **not** reconstruct a screenshot from `do_blocks()`, DOM parsing, GD, Imagick, PDF layout, or other server-side approximations. It also does not bundle Playwright, Puppeteer, Node.js, Chromium, JavaScript browser automation, or a screenshot SaaS.

A successful capture requires the hosting environment or a separate integration to provide a compatible `ScreenshotEngineInterface` through the `divi5_woocommerce_mcp_screenshot_engine` filter. When no real raster engine is available, the ability fails explicitly with:

```text
render_engine_unavailable
```

The caller cannot supply an arbitrary URL. The plugin derives the target from `post_id`, requires the target host/port to match the current WordPress site, signs non-public preview access with a short-lived HMAC bound to the post, user, target, and expiry, and validates returned PNG/JPEG bytes, dimensions, format, requested width, total pixels, byte size, and time limits.

See [`docs/divi-screenshot.md`](docs/divi-screenshot.md) for the renderer contract, limits, preview authorization, SSRF protections, and MCP image transport.

### Legacy compatibility abilities

The earlier v0.4 path-oriented abilities remain available as compatibility shims, including layout inspection, constrained save/update, module discovery/schema inspection, native insertion, delete, move, and duplicate operations. New integrations should prefer the clean-break runtime/document surface.

## Dependency integrity and reproducible builds

`composer.lock` is committed and is the authoritative dependency graph for CI and releases. Normal CI and production builds use `composer install`; they do not float dependencies with `composer update`.

The dedicated **Dependency Integrity** check:

1. requires `composer.lock`;
2. runs `composer validate --strict`;
3. installs the locked dependency graph;
4. verifies `wp-media/mcp-oauth` provenance and installed revision against the lock;
5. runs `composer audit --locked`;
6. verifies installation did not mutate the lock.

The OAuth integrity validator has no independent hard-coded expected commit. It verifies that the lock points to the trusted `wp-media/mcp-oauth` Git repository and that Composer installed exactly the revision recorded in the lock.

Pull requests also run GitHub **Dependency Review** with `fail-on-severity: high`.

An intentional dependency update therefore requires an explicit lock update in a reviewed commit; a moving development branch cannot silently change the dependency installed by CI or a release build.

## Build and CI

The main quality workflow covers:

- deterministic Composer installation;
- version consistency;
- PHP syntax;
- WordPress Coding Standards;
- PHPUnit;
- production dependency installation;
- distributable ZIP build and content verification;
- WordPress Plugin Check;
- distributable artifact upload.

Run the core checks locally with:

```bash
composer install
composer validate --strict
composer run validate-oauth-dependency
composer audit --locked
composer run validate-version
composer run lint:syntax
composer run lint
composer run test
```

Build the production ZIP with:

```bash
./scripts/build-zip.sh
```

The resulting asset is:

```text
build/mcp-bridge-for-divi-woocommerce.zip
```

Development-only files such as `.github/`, tests, scripts, local tooling configuration, dependency caches, and `node_modules` are excluded from the distributable.

## Release process

The project uses Semantic Versioning (`MAJOR.MINOR.PATCH`). The plugin version is centralized in `src/Version.php` and checked against the main plugin header and `readme.txt` stable tag. Tagged release validation additionally requires the Git tag version to match those files.

Release flow:

1. Feature pull request passes `PHP, tests, build, Plugin Check`, `Dependency Integrity`, and `Dependency Review`.
2. Feature is merged to `main`.
3. A release branch updates version metadata and public documentation.
4. The release pull request passes the same checks.
5. The release branch is merged to `main`.
6. Tag `vX.Y.Z` is created on the exact release commit.
7. The tag workflow validates the version, installs production dependencies from `composer.lock`, builds the ZIP, and publishes the GitHub Release.

Release tags should be protected against deletion and movement.

## Architecture and safety boundaries

The plugin is self-contained for its core MCP operation:

- PHP + WordPress APIs on the server;
- WordPress Abilities API as the capability registry;
- official `wordpress/mcp-adapter` package as the MCP bridge;
- Jetpack Autoloader to reduce dependency-version conflicts;
- runtime-derived Divi module and registry introspection rather than a static vendor catalog;
- WordPress block parsing/serialization for normalized reads and atomic persistence.

The plugin is not a generic SQL console, arbitrary PHP executor, arbitrary filesystem writer, generic URL fetcher, or unrestricted publishing surface. Publishing remains separate from draft editing.

Version 1.2.0 is not presented as a complete Visual Builder replacement. Design-variable/relative-color CRUD, complete authoritative state/preset/provider registries, Theme Builder/global systems, publish workflows, and browser computed-style inspection remain future work or runtime-dependent capabilities.

## Telemetry and WordPress.org handoff

During the temporary GitHub-distribution phase, usage telemetry and automatic fatal-error reporting are separate administrator settings under **Settings > MCP Bridge** and can be disabled independently.

Before WordPress.org submission, the repository still requires the documented handoff work: telemetry/error reporting must be reviewed and changed to explicit opt-in as required by current directory policy, the final WordPress.org slug/contributor must be set, and the temporary GitHub updater/Update URI must be removed from the WordPress.org package path.

The WordPress.org deployment workflow remains guarded and disabled unless the repository is explicitly configured after approval.

## Documentation

- [`docs/clean-break-runtime-document-foundation.md`](docs/clean-break-runtime-document-foundation.md) — clean-break runtime/document architecture.
- [`docs/divi-screenshot.md`](docs/divi-screenshot.md) — real-pixel screenshot renderer contract and security model.
- [`CHANGELOG.md`](CHANGELOG.md) — release history.
- [`SECURITY.md`](SECURITY.md) — security policy and reporting.

## License

GPL-2.0-or-later.
