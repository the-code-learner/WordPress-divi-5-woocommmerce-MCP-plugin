# Clean-break runtime/document foundation

This document describes the current clean-break runtime/document milestone of the approved Divi architecture.

## Primary pipeline

The next-major API is designed around a normalized Divi document model instead of exposing fragile native block details as the client contract:

`MCP request -> normalized Divi IR/document AST -> runtime schema/capability validation -> native Divi adapter -> WordPress block persistence -> render/inspect`

The v0.4.0 abilities remain available as compatibility shims while the new surface is built. They are not the primary API for the next major.

## Runtime and module introspection

### `divi-runtime-describe`

Reports the clean-break API generation/version, active runtime detection, registered compatible modules, provider provenance derived from the runtime block namespace, compatibility mode when it can be proven, and conservative capability negotiation.

A feature is reported as `supported` only when runtime metadata or an implemented primitive demonstrates it. Otherwise it is reported as `unknown` or `unavailable`.

The current milestone reports document get, validation and mutation as implemented. State editing, preset application, render and DOM/computed-style inspection remain unavailable until a proven native mapping or implementation exists.

### `divi-module-describe`

Describes one compatible runtime module and returns:

- provider and registration provenance;
- native/converted/legacy compatibility mode when runtime evidence exposes it;
- parent/ancestor/allowed-child constraints when available;
- a complete normalized parameter graph for introspection;
- a narrower mutation-facing parameter list containing only runtime-proven writable mappings;
- raw WordPress/Divi runtime schema and defaults for escape-hatch inspection.

The parameter graph preserves both a semantic path and native provenance. Runtime `attrName` metadata is normalized when present, including nested paths such as `content.innerContent`.

A parameter is exposed for semantic mutation only when the runtime proves both sides of the write contract:

1. a concrete persisted native value path; and
2. a value contract that can be validated without guessing, either a known scalar/container type or an explicit enum.

UI/schema paths and persisted paths are intentionally kept separate. For example, a control may appear under `module.advanced.sizing` while the persisted value is stored under `module.advanced.forceFullwidth.desktop.value`. The authoring surface follows observed persistence evidence, not the UI hierarchy.

Responsive devices/breakpoints, hover, sticky, presets, Design Variables/global values, units, enums and constraints are included only when runtime data demonstrates them.

## Third-party module discovery

There is no static catalog for Divi Engine, Divi Supreme, Divi Pixel or any other vendor.

Core `divi/*` modules are recognized directly. A third-party namespace is included only when the registered WordPress block type demonstrates Divi-module runtime evidence, for example Divi module categories or Divi-shaped module/style/metadata attributes. Provider identity is derived from the registered block namespace rather than from a vendor allowlist.

Third-party compatibility mode remains `unknown` unless the runtime exposes evidence for `native`, `converted` or `legacy`. Registration alone is not treated as proof of full authoring support, and an introspectable parameter remains read-only when its persisted path or value contract cannot be proven.

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
- clean-break authoring availability for runtime-recognized modules;
- optional raw native attributes via `include_native=true`.

### Handle contract

Numeric paths such as `0.2.0.0.2` are locators for inspection/debugging only. They are not node identity.

Every document read returns a SHA-256 `document_token`. Node handles are deterministic for that exact document snapshot and are marked with `handle_scope=document_snapshot`. Atomic mutation batches bind to the starting document token and resolve handles against the in-memory batch state, so insert/delete/move operations cannot silently retarget later operations after structural shifts.

If the persisted document changes, the token and handles change. A mutation request using a stale token fails and requires a fresh document read.

## `divi-document-validate`

Dry-runs a semantic mutation batch against one exact document snapshot without persistence. Validation returns machine-readable operation errors and applies the same runtime descriptor, hierarchy, path and value-contract rules used by persistence.

The batch is invalid when any operation is invalid. No partial persistence occurs during validation.

## `divi-document-mutate`

Persists a mutation batch only after the complete batch validates successfully in memory. The write path remains restricted to `draft`, `pending` and `auto-draft` posts and rejects stale document tokens.

Supported structural/semantic operations are exposed by the clean-break ability contract. State and preset operations currently fail with explicit machine-readable unavailable-mapping errors instead of synthesizing native paths.

Persistence uses WordPress block serialization and one post-content update after validation. The previous document revision is saved when the WordPress revision API is available.

## Security boundary

The clean-break API does not accept arbitrary raw-native write paths from the client. Mutation properties must resolve through the runtime-generated authoring parameter list, which is narrower than the introspection graph.

A runtime-proven path alone is not sufficient for authoring: the value type or enum must also be provable and validator-compatible. Unknown or opaque value contracts stay introspection-only.

Existing post capability checks, draft/pending/auto-draft gates, stale-token rejection, runtime module validation, hierarchy validation and constrained block serialization remain in force.

Dedicated raw-native authoring, explicit publish capability, state/preset native mapping, render and DOM/computed-style inspection are not implemented in this milestone and are not claimed as supported.
