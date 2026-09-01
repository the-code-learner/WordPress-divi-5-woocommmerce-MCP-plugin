<?php
/**
 * Bundled Chromium renderer tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Screenshot\BundledChromiumRenderer;
use PHPUnit\Framework\TestCase;

final class BundledChromiumRendererTest extends TestCase {
	public function test_allows_only_same_origin_browser_requests(): void {
		$origin = array(
			'scheme' => 'https',
			'host'   => 'example.test',
			'port'   => 443,
		);

		self::assertTrue( BundledChromiumRenderer::browser_request_allowed( 'https://example.test/style.css', $origin ) );
		self::assertTrue( BundledChromiumRenderer::browser_request_allowed( 'data:image/png;base64,AA==', $origin ) );
		self::assertTrue( BundledChromiumRenderer::browser_request_allowed( 'blob:https://example.test/abc', $origin ) );
		self::assertFalse( BundledChromiumRenderer::browser_request_allowed( 'https://cdn.example.test/style.css', $origin ) );
		self::assertFalse( BundledChromiumRenderer::browser_request_allowed( 'http://example.test/style.css', $origin ) );
		self::assertFalse( BundledChromiumRenderer::browser_request_allowed( 'https://example.test:8443/style.css', $origin ) );
		self::assertFalse( BundledChromiumRenderer::browser_request_allowed( 'http://169.254.169.254/latest/meta-data/', $origin ) );
		self::assertFalse( BundledChromiumRenderer::browser_request_allowed( 'file:///etc/passwd', $origin ) );
	}

	public function test_missing_binary_fails_closed(): void {
		$renderer = new BundledChromiumRenderer( '/definitely/missing/chrome-headless-shell' );
		$status   = $renderer->status( false );

		self::assertFalse( $status['ready'] );
		self::assertFalse( $status['binary_present'] );

		if ( $status['platform_supported'] ) {
			self::assertSame( 'renderer_binary_missing', $status['error_code'] );
		} else {
			self::assertSame( 'renderer_platform_unsupported', $status['error_code'] );
		}
	}

	public function test_renderer_identifier_is_stable(): void {
		$renderer = new BundledChromiumRenderer( '/tmp/not-used' );

		self::assertSame( 'bundled-chromium-cdp', $renderer->id() );
		self::assertSame( '152.0.7977.64', BundledChromiumRenderer::BUNDLE_VERSION );
		self::assertSame( 'linux-x86_64', BundledChromiumRenderer::PLATFORM );
	}
}
