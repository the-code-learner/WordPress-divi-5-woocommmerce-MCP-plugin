# Divi 5 + WooCommerce MCP

A WordPress plugin project for exposing controlled WordPress, Divi 5, WooCommerce, preview, and publishing capabilities to MCP clients.

> **Status:** early bootstrap (`0.1.0`). The repository structure, dependency model, security boundaries, CI, release checks, and WordPress.org deployment path are being established. This is not yet a production-ready Divi automation engine.

## Requirements

- WordPress **6.9+** (the Abilities API is part of core from 6.9).
- PHP **7.4+**.
- Divi 5 is detected at runtime; it is not bundled.
- WooCommerce is optional and detected at runtime; it is not bundled.
- Composer is required to build a distributable package because the official WordPress MCP Adapter and its dependencies are vendored into the release ZIP.

## Architecture

The plugin is designed to be self-contained for WordPress.org distribution:

- PHP + WordPress APIs on the server.
- The WordPress Abilities API as the capability registry.
- The official `wordpress/mcp-adapter` package as the MCP bridge.
- Jetpack Autoloader to reduce dependency-version conflicts with other plugins.
- Browser-side JavaScript/CSS for future preview and DOM/CSS inspection features.
- No required Playwright, Puppeteer, Chromium, Node daemon, Docker service, or SaaS at runtime.

Current source layout:

```text
src/
  Audit/
  Divi/
  MCP/
  Preview/
  Security/
  WooCommerce/
  WordPress/
assets/
  css/
  js/
languages/
tests/
.github/workflows/
```

The initial implementation exposes a read-only status Ability so the project has a real integration seam while keeping Divi-specific write behavior out of the bootstrap commit.

## Security model

The project is intended to enforce least privilege at every Ability through WordPress capability checks and explicit `permission_callback` functions. Planned MCP scopes are:

- `wordpress:read`
- `wordpress:write`
- `divi:read`
- `divi:write`
- `woocommerce:read`
- `woocommerce:write`
- `preview:read`
- `publish`

The target authentication model is OAuth 2.1 Authorization Code + PKCE, with WordPress Application Passwords retained only as an advanced fallback where appropriate. Publish operations are intentionally separated from editing operations and are expected to require an explicit publish gate, revision creation, and audit logging.

## Preview model

Preview is expected to use real WordPress rendering in a browser context. Responsive controls and a DOM/CSS inspector will live in the plugin UI. The project explicitly does **not** require a headless browser renderer at runtime.

## Roadmap

### Foundation

- [x] SemVer bootstrap at `0.1.0`.
- [x] WordPress 6.9+ / PHP 7.4+ baseline.
- [x] Official MCP Adapter dependency.
- [x] Divi and WooCommerce runtime detection.
- [x] Read-only status Ability.
- [x] CI, distributable ZIP workflow, and WordPress.org deployment guardrails.
- [ ] Confirm final WordPress.org slug and contributor username before submission.

### WordPress abilities

- [ ] Posts/pages CRUD, drafts, revisions, and media.
- [ ] Capability map for read/write/publish operations.
- [ ] Audit log for MCP-triggered mutations.

### Divi 5

- [ ] Structure and module discovery.
- [ ] Semantic module editing.
- [ ] Design properties and responsive overrides.
- [ ] Design variables, presets, and breakpoints.
- [ ] Theme Builder support after the relevant APIs are stable and verified.

### WooCommerce

- [ ] Optional product/catalog reads.
- [ ] Permission-gated product mutations.
- [ ] Order/customer operations only after privacy and capability review.

### Preview and QA

- [ ] WordPress-rendered preview endpoint/UI.
- [ ] Responsive preview controls.
- [ ] Browser-side DOM/CSS inspector.
- [ ] Publish gate with revision comparison.

## Development

```bash
composer install
composer validate --strict
composer run lint
composer run test
composer run validate-version
```

The development checkout is not the WordPress.org package. Production builds must run Composer with `--no-dev` and include `vendor/`.

## Build

The CI workflow uses WP-CLI `dist-archive`, which honors `.distignore`, to build:

```text
build/divi-5-woocommerce-mcp.zip
```

Development-only files such as `.github/`, `tests/`, local tooling configuration, and dependency caches are excluded from the distributable.

## Versioning and release

This project uses SemVer (`MAJOR.MINOR.PATCH`). Version `0.1.0` is centralized in `src/Version.php` and is checked against the plugin header and `readme.txt` stable tag.

Release flow:

1. Feature branch.
2. Pull request.
3. CI and Plugin Check.
4. Merge to `main`.
5. Bump version and changelog.
6. Tag `vX.Y.Z`.
7. Build and publish the GitHub Release ZIP.
8. After WordPress.org approval, approve the `wordpress-production` GitHub Environment.
9. Deploy the same version to WordPress.org SVN `trunk` and `tags/X.Y.Z`.

The WordPress.org deploy job is disabled unless repository variable `WORDPRESS_ORG_DEPLOY_ENABLED` is set to `true`. This prevents accidental SVN deployment before the plugin has been approved and an SVN repository exists.

## First WordPress.org submission

Before enabling deployment:

1. Create and verify a WordPress.org account.
2. Replace the contributor placeholder in `readme.txt` with the exact public WordPress.org username.
3. Set a tested WordPress version only after the package has actually been tested against that version.
4. Build the production ZIP and run Plugin Check/readme validation.
5. Submit the ZIP through the WordPress.org Plugin Developer submission flow.
6. Address review feedback.
7. After approval and SVN assignment, configure GitHub secrets `SVN_USERNAME` and `SVN_PASSWORD`.
8. Set repository variable `WORDPRESS_ORG_SLUG` to the assigned slug.
9. Set `WORDPRESS_ORG_DEPLOY_ENABLED=true`.
10. Configure GitHub Environment `wordpress-production` with a required reviewer/manual approval before the first deploy.

## License

GPL-2.0-or-later. WordPress.org requires GPL-compatible licensing, so the distributed plugin code cannot carry a non-commercial restriction. Commercial protection, if needed, should be handled outside the WordPress.org code license (for example through trademarks or optional services) without restricting the GPL-covered plugin.
