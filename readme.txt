=== MCP Bridge for Divi 5 and WooCommerce ===
Contributors: TODO-wordpress-org-username
Tags: mcp, divi, woocommerce, ai, automation
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 0.1.2
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure MCP foundations for WordPress, Divi 5, WooCommerce, browser-based preview, and controlled publishing workflows.

== Description ==

MCP Bridge for Divi 5 and WooCommerce is an early-stage plugin that builds on the WordPress Abilities API and the official WordPress MCP Adapter.

Version 0.1.2 adds privacy-preserving usage telemetry and automatic fatal-error reporting for the current pre-WordPress.org GitHub distribution. Usage telemetry and error reporting are separate administrator settings and are enabled by default in this temporary distribution phase. Either setting can be disabled under Settings > MCP Bridge.

The usage heartbeat sends only a random local installation identifier, plugin version, WordPress version, PHP major/minor version, and booleans indicating whether Divi and WooCommerce are detected. The telemetry service stores only a keyed hash of the installation identifier. Automatic error reports are restricted to fatal errors originating inside this plugin and include only sanitized plugin-owned diagnostics.

The plugin does not intentionally send the site URL or domain, administrator email, usernames or user IDs, post/page/product/order/customer content, database values, cookies, request bodies, tokens, secrets, or arbitrary plugin/theme lists. The telemetry receiver is hosted at the project Cloudflare Workers endpoint. Error records are retained for 30 days and inactive installation records for 120 days by the receiver configuration.

Before submission to WordPress.org, usage telemetry and automatic error reporting must both be changed to disabled-by-default explicit opt-in, and the privacy/readme disclosure must be reviewed against the current Plugin Directory requirements.

The project is designed to remain self-contained for its core MCP functionality. It does not require Playwright, Puppeteer, Chromium, a Node daemon, Docker, or an external SaaS service for MCP operation. The pre-WordPress.org telemetry service is optional and can be disabled.

Planned capabilities include permission-gated WordPress CRUD, semantic Divi 5 editing, optional WooCommerce operations, WordPress-rendered preview, browser-side DOM/CSS inspection, revisions, audit logging, and a separate publish gate.

Before first WordPress.org submission, replace the Contributors placeholder with the exact WordPress.org username. The temporary GitHub updater and Update URI are intended for GitHub-distributed builds and will be removed from the WordPress.org distribution.

== Installation ==

1. Install a production ZIP built from a tagged release.
2. Activate the plugin.
3. Confirm that WordPress 6.9 or newer is running.
4. Divi 5 and WooCommerce are detected if installed; they are not bundled.
5. GitHub release checks are enabled by default. They can be disabled under Settings > MCP Bridge.
6. Usage telemetry is enabled by default in current GitHub-distributed builds and can be disabled independently.
7. Automatic fatal-error reporting is enabled by default in current GitHub-distributed builds and can be disabled independently.

The GitHub source checkout requires Composer and is not itself the production package.

== Frequently Asked Questions ==

= Does this plugin include Divi 5 or WooCommerce? =

No. Both products are detected at runtime and remain separate dependencies.

= Does this require a headless browser or Node.js service? =

No. The target runtime is PHP + WordPress + browser-side JavaScript. Node tooling is not required at runtime.

= How are updates delivered before the WordPress.org listing is available? =

Stable GitHub Releases are used temporarily. The checker ignores GitHub prereleases, does not fall back to tags or branches, and requires the production release asset named `mcp-bridge-for-divi-woocommerce.zip`.

= Can I disable GitHub update checks? =

Yes. Go to Settings > MCP Bridge and disable GitHub updates. The option is enabled by default.

= What telemetry is sent in version 0.1.2? =

A low-frequency heartbeat sends a random installation identifier, plugin version, WordPress version, PHP major/minor version, and Divi/WooCommerce detection booleans. The first heartbeat is delayed and later heartbeats are scheduled approximately weekly with jitter.

= What error information is sent? =

Only fatal errors whose source file is inside this plugin are automatically reported. Messages are redacted client-side for URLs, email addresses, absolute paths, and common secret/token patterns. Stack information is limited to plugin-owned relative paths and at most ten frames.

= Can I disable telemetry and error reporting? =

Yes. Usage telemetry and automatic error reporting are separate Settings > MCP Bridge options. Both are enabled by default only during the current pre-WordPress.org GitHub distribution phase and can be disabled independently.

= Is the plugin production ready? =

No. Version 0.1.2 is still an early foundation release with a minimal read-only Ability and infrastructure for future implementation.

= Why is the license GPL-2.0-or-later? =

WordPress.org requires GPL-compatible licensing. A non-commercial restriction is not compatible with WordPress.org distribution.

== Screenshots ==

Screenshots will be added after the preview/admin UI is implemented.

== Changelog ==

= 0.1.2 =
* Add separate usage telemetry and automatic error-reporting administrator settings.
* Enable both settings by default for the temporary pre-WordPress.org GitHub distribution, with independent opt-out controls.
* Add a random local installation ID, delayed weekly heartbeat scheduling with jitter, non-blocking HTTP delivery, payload allowlists, and client-side redaction.
* Limit automatic error reports to fatal errors originating inside the plugin.
* Add WordPress privacy-policy helper content and tests covering opt-out, payload minimization, sanitization, identity generation, and scheduling.
* Document the mandatory future migration to explicit opt-in before WordPress.org submission.

= 0.1.1 =
* Update the official WordPress MCP Adapter dependency to the 0.6.x stable line.
* Add a temporary stable-only GitHub Releases updater using the production ZIP asset.
* Enable GitHub update checks by default with an administrator setting to disable them.
* Add the plugin Update URI for external distribution.
* Add unit coverage for the update setting.

= 0.1.0 =
* Initial repository and plugin bootstrap.
* Add official WordPress MCP Adapter dependency through Composer.
* Add Divi and WooCommerce runtime detection.
* Add a permission-gated, read-only integration status Ability.
* Add SemVer validation, CI, Plugin Check, production ZIP build, and guarded WordPress.org deployment workflow.
