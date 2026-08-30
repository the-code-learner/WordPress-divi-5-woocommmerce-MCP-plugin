# Clean-break runtime/document foundation

This document describes the first read/introspection milestone of the approved clean-break Divi architecture.

## Primary pipeline

The next-major API is designed around a normalized Divi document model instead of exposing fragile native block details as the client contract:

`MCP request -> normalized Divi IR/document AST -> runtime schema/capability validation -> native Divi adapter -> WordPress block persistence -> render/inspect`

The v0.4.0 abilities remain available as compatibility shims while the new surface is built. They are not the primary API for the next major.

## Read foundation abilities

### `divi-runtime-describe`

Reports the clean-break API generation/version, active runtime detection, registered compatible modules, provider provenance derived from the runtime block namespace, compatibility mode when it can be proven, and capability negotiation.

Capability values are intentionally conservative. A feature is reported as `supported` only when runtime metadata or an implemented primitive demonstrates it. Otherwise it is reported as `unknown` or `unavailable`.

The first milestone explicitly reports clean-break document validation, mutation, render and DOM/computed-style inspection as unavailable until those milestones are implemented.

### `divi-module-describe`

Describes one compatible runtime module and returns:

- provider and registration provenance;
- native/converted/legacy compatibility mode when runtime evidence exposes it;
- parent/ancestor/allowed-child constraints when available;
- a normalized parameter graph;
- raw WordPress/Divi runtime schema and defaults for escape-hatch inspection.

The parameter graph preserves both a semantic path and native provenance. Runtime `attrName` metadata is normalized when present, including nested paths such as `content.innerContent`. Responsive devices/breakpoints, hover, sticky, presets, Design Variables/global values, units, enums and constraints are included only when the runtime data demonstrates them.

## Third-party module discovery

There is no static catalog for Divi Engine, Divi Supreme, Divi Pixel or any other vendor.

Core `divi/*` modules are recognized directly. A third-party namespace is included only when the registered WordPress block type demonstrates Divi-module runtime evidence, for example Divi module categories or Divi-shaped module/style/metadata attributes. Provider identity is derived from the registered block namespace rather than from a vendor allowlist.

Third-party compatibility mode remains `unknown` unless the runtime exposes evidence for `native`, `converted` or `legacy`. Registration alone is not treated as proof of full authoring support.

## `divi-document-get`

Returns a normalized AST with:

- `module_type`;
- semantic-path keyed `normalized_properties`;
- child nodes;
- current `numeric_path`;
- snapshot-scoped `handle`;
- parent handle;
- provider/provenance and compatibility metadata;
- nesting/capability data;
- optional raw native attributes via `include_native=true`.

### Handle contract

Numeric paths such as `0.2.0.0.2` are locators for inspection/debugging only. They are not node identity.

Every document read returns a SHA-256 `document_token`. Node handles are deterministic for that exact document snapshot and are marked with `handle_scope=document_snapshot`. Future atomic mutation batches will bind to the starting document token and resolve handles before structural edits, so insert/delete/move operations cannot invalidate the identity of later operations in the same batch.

If the persisted document changes, the token and handles change. A future mutation request using a stale token must fail rather than silently target a shifted numeric path.

## Security boundary

This milestone is read-only and does not weaken v0.4.0 write protections. Existing draft/pending/auto-draft gates, runtime validation, hierarchy validation and constrained native serialization remain unchanged.

The clean-break raw-native write path, atomic validation/mutation engine, explicit publish capability, render primitive and DOM/computed-style inspector are subsequent milestones and are not claimed as implemented here.
