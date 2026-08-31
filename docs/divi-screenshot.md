# Divi server-side screenshot ability

`divi5-woocommerce-mcp/divi-screenshot` is a read-only MCP ability for obtaining a real visual rendering of a WordPress/Divi page at an arbitrary viewport width.

It is intentionally separate from `divi-render`. `divi-render` executes WordPress block callbacks and inspects server-side markup; `divi-screenshot` is allowed to succeed only when a compatible backend renderer can produce the actual frontend pixels after HTML/CSS layout.

## Input

Required:

- `post_id` — WordPress post/page ID in the current site.
- `width` — viewport width in pixels, from 240 through 4096.

Optional:

- `full_page` — defaults to `true`.
- `height` — viewport height when `full_page=false`; defaults to 900 when omitted.
- `format` — `png` or `jpeg`, defaults to `png`.
- `quality` — 1 through 100 for JPEG output.

Examples:

```text
divi-screenshot(post_id=85, width=1440, full_page=true)
divi-screenshot(post_id=85, width=390, full_page=true)
divi-screenshot(post_id=85, width=1440, height=900, full_page=false)
```

The width is not limited to Divi breakpoints. Intermediate values such as 1366, 1536, 1728, or 1920 are valid.

## Real-pixel requirement

The plugin does **not** turn `do_blocks()`, `DOMDocument`, GD, Imagick, a PDF layout, or reconstructed markup into a pseudo-screenshot. Those approaches do not execute the frontend CSS layout engine and therefore cannot verify the layout problems this ability is intended to detect.

The plugin also does not bundle Playwright, Puppeteer, Node.js, JavaScript browser automation, Chromium, or a SaaS screenshot service.

A successful screenshot requires a backend renderer implementing `ScreenshotEngineInterface`. If none is registered, or the registered renderer is unavailable, the ability fails explicitly with:

```text
error_code = render_engine_unavailable
```

This is an expected capability-negotiation result, not a fallback to an approximate preview.

## Renderer provider contract

A hosting-specific or separately packaged integration can register one renderer through the WordPress filter:

```php
add_filter(
    'divi5_woocommerce_mcp_screenshot_engine',
    static function ( $engine, array $context ) {
        return new SiteScreenshotEngine();
    },
    10,
    2
);
```

`SiteScreenshotEngine` must implement:

```php
CodeLearner\Divi5WooCommerceMCP\Divi\ScreenshotEngineInterface
```

The engine receives only a URL generated and validated by WordPress plus normalized rendering parameters. It must return real PNG/JPEG bytes:

```php
array(
    'success'       => true,
    'image_data'    => $raw_image_bytes,
    'render_method' => 'site-native-raster-engine',
    'warnings'      => array(),
);
```

A provider must not accept an arbitrary caller-supplied URL, must not send the page URL to an external screenshot service, and must honor the supplied timeout and size limits.

## MCP image transport

On success the ability returns the raw image bytes using the MCP Adapter's native image-result shape:

```text
type = image
mimeType = image/png | image/jpeg
results = raw image bytes
```

The official WordPress MCP Adapter converts that result to an MCP image content block. The plugin does not Base64-encode the image itself and does not create a WordPress Media attachment, so the operation remains read-only.

Metadata is attached to the image result and includes:

- `success`
- `post_id`
- `page_url`
- `width`
- `height`
- `full_page`
- `format`
- `quality`
- `image_file`
- `image_width`
- `image_height`
- `render_method`
- `warnings`
- `error_code`
- `error_message`

## Draft and private preview authorization

Published posts use their normal same-site permalink.

For non-published content the plugin generates the normal WordPress preview URL and adds a short-lived HMAC authorization token bound to:

- the exact post ID;
- the current MCP WordPress user;
- the exact preview target;
- a short expiration time.

The preview request is accepted only if that user still has `edit_post` permission for the requested post. The temporary authorization URL is passed only to the local renderer and is not returned as `page_url` in the MCP response. The WordPress admin bar is suppressed for this render path.

## SSRF and URL restrictions

The ability never accepts a URL as input. It resolves the page from `post_id`, generates the permalink/preview URL using WordPress APIs, and rejects URLs whose host/port do not match the current WordPress site's `home_url()`.

Consequently a caller cannot use `divi-screenshot` as a generic URL fetcher or screenshot arbitrary hosts.

## Resource limits

Current guardrails:

- viewport width: 240–4096 px;
- viewport height: 100–8192 px when `full_page=false`;
- full image height: maximum 30,000 px;
- total image pixels: maximum 100,000,000;
- returned image bytes: maximum 25 MiB;
- renderer timeout supplied by the plugin: 30 seconds.

Renderer output is checked before it is returned. The plugin validates the PNG/JPEG signature, actual image dimensions, requested format, requested width, and viewport height for non-full-page captures.

## Capability negotiation

`divi-runtime-describe` exposes a separate `capabilities.screenshot` entry. It is `supported` only when a registered engine explicitly reports itself available; otherwise it is `unavailable` with evidence explaining why.

This keeps server-side markup rendering and actual visual rendering as two distinct, truthful capabilities.
