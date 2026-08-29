# Contributing

## Workflow

1. Create a feature branch from `main`.
2. Keep changes focused and update tests/documentation.
3. Run `composer check`.
4. Open a pull request.
5. Merge only after CI is green and review is complete.

## Versioning

The project follows Semantic Versioning. Version metadata must stay synchronized between:

- `src/Version.php`;
- the main plugin `Version:` header;
- `readme.txt` `Stable tag:`;
- Git tag `vX.Y.Z`;
- GitHub Release.

Run `composer run validate-version` before release.

## WordPress.org constraints

Contributions intended for the WordPress.org package must remain GPL-compatible and must not add non-commercial restrictions, hidden paid gates for bundled functionality, or mandatory external runtime services.
