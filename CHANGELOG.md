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
