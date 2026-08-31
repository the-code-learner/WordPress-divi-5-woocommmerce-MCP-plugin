<?php
/**
 * Screenshot renderer unit tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Divi\ScreenshotRenderer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ScreenshotRendererTest extends TestCase {
	public function test_reads_png_dimensions_without_rendering_or_reconstructing_layout(): void {
		$png = "\x89PNG\r\n\x1a\n"
			. pack( 'N', 13 )
			. 'IHDR'
			. pack( 'N', 390 )
			. pack( 'N', 4872 )
			. "\x08\x06\x00\x00\x00";

		$method = new ReflectionMethod( ScreenshotRenderer::class, 'image_info' );
		$method->setAccessible( true );
		$result = $method->invoke( null, $png );

		self::assertIsArray( $result );
		self::assertSame( 'png', $result['format'] );
		self::assertSame( 'image/png', $result['mime_type'] );
		self::assertSame( 390, $result['width'] );
		self::assertSame( 4872, $result['height'] );
	}

	public function test_rejects_non_image_renderer_output(): void {
		$method = new ReflectionMethod( ScreenshotRenderer::class, 'image_info' );
		$method->setAccessible( true );

		self::assertNull( $method->invoke( null, '<html>not an image</html>' ) );
	}

	public function test_signed_preview_target_excludes_authentication_parameters(): void {
		$method = new ReflectionMethod( ScreenshotRenderer::class, 'canonical_target' );
		$method->setAccessible( true );
		$target = $method->invoke(
			null,
			'https://example.test/page/?preview=true&foo=bar&_divi_mcp_ss_sig=abc&_divi_mcp_ss_exp=123&_divi_mcp_ss_target=def&_divi_mcp_ss_user=7&_divi_mcp_ss_post=85&_divi_mcp_screenshot=1'
		);

		self::assertSame( '/page/?foo=bar&preview=true', $target );
	}
}
