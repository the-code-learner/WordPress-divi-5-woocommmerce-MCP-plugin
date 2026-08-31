# MCP Bridge for Divi 5 and WooCommerce

A WordPress plugin that exposes controlled WordPress and Divi 5 capabilities to MCP clients through the WordPress Abilities API and the official `wordpress/mcp-adapter` bridge.

The current primary Divi surface is the **clean-break Runtime + Document API**: runtime-driven module discovery, normalized document reads, dry-run validation, and atomic semantic mutation without exposing arbitrary native attribute writes as the client contract.

> **Current stable version:** `1.0.0`  
> **Clean-break API generation:** `clean-break-1`  
> **Clean-break API version:** `1.0.0-alpha.1`  
> **Primary API:** `clean-break-runtime-document`  
> **Stable release:** [v1.0.0](https://github.com/the-code-learner/WordPress-divi-5-woocommmerce-MCP-plugin/releases/tag/v1.0.0)

## Requirements

- WordPress **6.9+** because the plugin uses the core Abilities API.
- PHP **7.4+**.
- Divi 5 for Divi runtime discovery and native Divi authoring. Divi is detected at runtime and is not bundled.
- WooCommerce is optional and detected at runtime; it is not bundled.
- Composer is required for development builds. Production release ZIPs include the MCP adapter and runtime dependencies.

## Installation

For the supported GitHub-distribution path, install the release asset from the stable release:

`mcp-bridge-for-divi-woocommerce.zip`

Upload it through **Plugins > Add New > Upload Plugin** in WordPress, install, and activate it.

For a development checkout:

```bash
composer install
```

The development checkout is not the distributable package; production builds vendor dependencies and are created by `scripts/build-zip.sh`.

## Stable self-update

The plugin includes a self-update path restricted to this plugin and the stable GitHub release channel.

Relevant abilities:

- `divi5-woocommerce-mcp/get-update-status`
- `divi5-woocommerce-mcp/update-self`

`get-update-status` forces a fresh stable-release check. `update-self` requires an exact SemVer `expected_version` and rejects candidates that do not match the expected plugin target, stable version format, or allowed GitHub release asset source.

Example update request:

```json
{
  "expected_version": "1.0.0"
}
```

The update ability requires the WordPress `update_plugins` capability.

## Main abilities

### Status and update

- `divi5-woocommerce-mcp/get-status`
- `divi5-woocommerce-mcp/get-update-status`
- `divi5-woocommerce-mcp/update-self`

### Clean-break Runtime + Document API

- `divi5-woocommerce-mcp/divi-runtime-describe`
- `divi5-woocommerce-mcp/divi-module-describe`
- `divi5-woocommerce-mcp/divi-document-get`
- `divi5-woocommerce-mcp/divi-document-validate`
- `divi5-woocommerce-mcp/divi-document-mutate`

The clean-break abilities are the primary Divi API in `v1.0.0`. Legacy `v0.4` Divi abilities are retained as compatibility shims, not as the preferred authoring surface.

## Runtime and module discovery

### `divi-runtime-describe`

Describes the active Divi runtime and conservatively negotiates capabilities from observed runtime metadata. It reports discovered compatible modules, provider provenance, API generation/version, compatibility information, and feature capability states such as `supported`, `unknown`, or `unavailable`.

Capabilities are not assumed from product names. The descriptor only reports support when runtime evidence or an implemented primitive demonstrates it.

### `divi-module-describe`

Describes one compatible registered module and exposes a normalized parameter graph together with raw runtime schema information for inspection.

The descriptor separates:

- semantic property paths used by MCP clients;
- runtime/native provenance;
- runtime-proven writable mappings;
- value contracts such as scalar/container types or explicit enums;
- runtime-discovered responsive/state metadata where available.

A property is authorable only when the runtime proves both a persisted native value path and a value contract that can be validated without guessing.

## Document GET

### `divi-document-get`

Returns a normalized Divi document AST for a WordPress post. The document includes snapshot-scoped node handles, current numeric paths, module/provider provenance, normalized semantic properties, and optionally raw native attributes with `include_native=true`.

Each document read also returns a SHA-256 `document_token` representing that exact persisted document snapshot.

Numeric paths are locators for inspection; they are not stable node identity. Mutation batches should use the snapshot-scoped handles returned by the document read.

## Validation

### `divi-document-validate`

Dry-runs a semantic mutation batch against one exact `document_token` without persistence.

Validation uses the same runtime descriptor, hierarchy, property-path, and value-contract rules used by persistence. If any operation is invalid, the batch is invalid. Validation does not partially persist a batch.

Use validation before mutation whenever possible.

## Atomic mutation

### `divi-document-mutate`

Validates the complete batch in memory and persists only when every operation is valid. Persistence is restricted to posts in `draft`, `pending`, or `auto-draft` status.

The supported operation contract includes structural and semantic operations such as `insert`, `set`, `delete`, `move`, `duplicate`, and `responsive`. `state` and `preset` are present in the operation vocabulary but currently fail with explicit unavailable-mapping errors rather than inventing native paths.

Persistence serializes the validated WordPress block tree and performs one post-content update. A previous revision is saved when the WordPress revision API is available.

## Optimistic concurrency with `document_token`

Every validation or mutation batch is bound to the exact document snapshot returned by `divi-document-get`.

If the persisted document changes, its token changes. A request that submits an old token is rejected with `stale_document_token`; the client must read the document again and re-plan against the new snapshot.

This prevents a mutation planned against one tree from silently applying to a different tree after another edit.

## Semantic property paths

Clients should author through semantic property paths exposed by the runtime-generated module descriptor, not by directly writing arbitrary raw native attributes.

A verified `v1.0.0` example for the Divi Image module is:

```text
semantic property: module.advanced.sizing.forceFullwidth
native property:   module.advanced.forceFullwidth
native desktop:    module.advanced.forceFullwidth.desktop.value
```

A semantic `set` operation uses the semantic path:

```json
{
  "op": "set",
  "handle": "<document-snapshot-handle>",
  "property": "module.advanced.sizing.forceFullwidth",
  "value": "on"
}
```

The semantic-to-native mapping is resolved from the active Divi runtime/module descriptors. Clients should not hard-code the native path as the write contract. A direct property that is not present in the runtime authoring schema is rejected with `property_not_in_runtime_schema`.

## Supported and unavailable boundaries

The clean-break runtime descriptor is intentionally conservative. In `v1.0.0`:

- runtime/module discovery is implemented;
- nested module metadata is supported when exposed by the runtime;
- responsive metadata and breakpoints are runtime-discoverable;
- hover/sticky/preset metadata may be discoverable when exposed by registered runtime modules;
- raw native read is supported on descriptor/document reads where requested;
- document GET, validation, and atomic mutation are implemented;
- **raw native write is unavailable**;
- **state editing is unavailable** (`state_mapping_unavailable`);
- **preset application is unavailable** (`preset_mapping_unavailable`);
- **render is unavailable**;
- **DOM/computed-style inspection is unavailable**.

`design_variables` and `global_values` are runtime-negotiated fields and must not be assumed to be supported unless the active runtime descriptor proves support.

## Safety model

The clean-break authoring surface is designed to fail closed rather than synthesize unsupported Divi writes:

- prefer `draft`, `pending`, or `auto-draft` content for mutation workflows;
- call `divi-document-validate` before `divi-document-mutate`;
- bind every validation/mutation batch to the current `document_token`;
- re-read after `stale_document_token` instead of retrying blindly;
- use semantic property paths exposed by `divi-module-describe`;
- do not bypass the semantic schema with arbitrary raw native paths;
- require WordPress post capability checks for document access;
- persist only after the full batch validates successfully.

The clean-break API does not provide an arbitrary raw-native authoring escape hatch.

## Architecture

The plugin is self-contained for its core MCP operation:

- PHP + WordPress APIs on the server;
- WordPress Abilities API as the capability registry;
- official `wordpress/mcp-adapter` package as the MCP bridge;
- Jetpack Autoloader to reduce dependency-version conflicts;
- Divi runtime/module introspection derived from registered runtime data rather than a static vendor catalog;
- WordPress block parsing/serialization for normalized document reads and atomic persistence.

Current source areas include:

```text
src/
  Admin/
  Audit/
  Divi/
  MCP/
  Preview/
  Security/
  Telemetry/
  Updates/
  WooCommerce/
  WordPress/
docs/
tests/
scripts/
.github/workflows/
```

See [`docs/clean-break-runtime-document-foundation.md`](docs/clean-break-runtime-document-foundation.md) for the lower-level clean-break contract and design boundary.

## Telemetry and privacy during GitHub distribution

The plugin includes separate settings under **Settings > MCP Bridge** for usage telemetry and automatic error reporting. These are separate from the GitHub updater.

Telemetry/error payloads are intentionally constrained and sanitized; they do not use arbitrary WordPress content as telemetry input. See the plugin settings and source under `src/Telemetry/` for the current behavior before deploying into privacy-sensitive environments.

## Development

```bash
composer install
composer validate --strict
composer run lint
composer run test
composer run validate-version
```

## Build and CI

CI validates the PHP project, tests, version consistency, and distributable boundaries. Release builds run `scripts/build-zip.sh` and produce:

```text
build/mcp-bridge-for-divi-woocommerce.zip
```

Development-only files are excluded from the release package through `.distignore`.

The `v1.0.0` stable release asset is `mcp-bridge-for-divi-woocommerce.zip` with SHA-256:

```text
af82f2b44af5eb5a84256bd65913f3fb880cc99559159dd12baefaf3311191ef
```

Release: [https://github.com/the-code-learner/WordPress-divi-5-woocommmerce-MCP-plugin/releases/tag/v1.0.0](https://github.com/the-code-learner/WordPress-divi-5-woocommmerce-MCP-plugin/releases/tag/v1.0.0)

## Versioning

The project uses SemVer (`MAJOR.MINOR.PATCH`). The plugin version is centralized in `src/Version.php` and checked against the plugin header and release metadata by the repository validation tooling.

Do not infer clean-break API maturity solely from the plugin SemVer: the plugin is `1.0.0`, while the clean-break API descriptor currently reports API version `1.0.0-alpha.1` under generation `clean-break-1`.

## License

GPL-2.0-or-later.
