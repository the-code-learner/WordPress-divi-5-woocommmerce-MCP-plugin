# Security Policy

## Supported versions

Only the latest released version is supported while the project is in the `0.x` phase.

## Reporting a vulnerability

Do not publish exploitable security details in a public issue. Use GitHub's private vulnerability reporting feature when enabled for this repository. If private reporting is not available, contact the repository owner through a private channel before disclosure.

Please include:

- affected version/commit;
- reproduction steps;
- security impact;
- any suggested mitigation.

## Security principles

- Every externally exposed Ability must have an explicit `permission_callback`.
- Read, write, and publish permissions are separated.
- Publishing must remain an explicit gated operation.
- Inputs and outputs should use schemas and validation.
- Sensitive values must not be written to audit logs.
- Divi and WooCommerce are detected, not bundled.
- No mandatory headless browser, daemon, Docker service, or SaaS dependency is permitted at runtime.
- OAuth 2.1 Authorization Code + PKCE is the target primary remote-authentication model; WordPress Application Passwords are an advanced fallback.
