=== MCP Bridge for Divi 5 and WooCommerce ===
Contributors: TODO-wordpress-org-username
Tags: mcp, divi, woocommerce, ai, automation
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 0.1.1
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure MCP foundations for WordPress, Divi 5, WooCommerce, browser-based preview, and controlled publishing workflows.

== Description ==

MCP Bridge for Divi 5 and WooCommerce is an early-stage plugin that builds on the WordPress Abilities API and the official WordPress MCP Adapter.

Version 0.1.1 hardens the MCP foundation and adds a temporary GitHub Releases update channel while the plugin is not yet distributed through WordPress.org. GitHub update checks are enabled by default and can be disabled by an administrator under Settings > MCP Bridge.

The project is designed to remain self-contained for WordPress.org distribution. It does not require Playwright, Puppeteer, Chromium, a Node daemon, Docker, or a SaaS service at runtime.

Planned capabilities include permission-gated WordPress CRUD, semantic Divi 5 editing, optional WooCommerce operations, WordPress-rendered preview, browser-side DOM/CSS inspection, revisions, audit logging, and a separate publish gate.

Before first submission, replace the Contributors placeholder with the exact WordPress.org username. The temporary GitHub updater and Update URI are intended for GitHub-distributed builds and will be removed from the WordPress.org distribution.

== Installation ==

1. Install a production ZIP built from a tagged release.
2. Activate the plugin.
3. Confirm that WordPress 6.9 or newer is running.
4. Divi 5 and WooCommerce are detected if installed; they are not bundled.
5. GitHub release checks are enabled by default. They can be disabled under Settings > MCP Bridge.

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

= Is the plugin production ready? =

No. Version 0.1.1 is still an early foundation release with a minimal read-only Ability and infrastructure for future implementation.

= Why is the license GPL-2.0-or-later? =

WordPress.org requires GPL-compatible licensing. A non-commercial restriction is not compatible with WordPress.org distribution.

== Screenshots ==

Screenshots will be added after the preview/admin UI is implemented.

== Changelog ==

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
