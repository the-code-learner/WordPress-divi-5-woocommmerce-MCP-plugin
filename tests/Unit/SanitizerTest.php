<?php
/**
 * Telemetry sanitizer tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Telemetry\Sanitizer;
use PHPUnit\Framework\TestCase;

final class SanitizerTest extends TestCase {
	public function test_message_redacts_private_values(): void {
		$message = 'Failure at /var/www/site/wp-content/plugins/mcp/src/Foo.php for admin@example.com '
			. 'https://example.com/private?token=abc password=hunter2 Authorization=BearerSecret';
		$clean = Sanitizer::sanitize_message( $message );

		self::assertStringNotContainsString( '/var/www/site', $clean );
		self::assertStringNotContainsString( 'admin@example.com', $clean );
		self::assertStringNotContainsString( 'example.com', $clean );
		self::assertStringNotContainsString( 'hunter2', $clean );
		self::assertStringNotContainsString( 'BearerSecret', $clean );
		self::assertStringContainsString( '[path]', $clean );
		self::assertStringContainsString( '[email]', $clean );
		self::assertStringContainsString( '[url]', $clean );
	}

	public function test_frames_keep_only_plugin_owned_relative_paths(): void {
		$frames = array(
			array(
				'file'     => '/srv/www/wp-content/plugins/mcp/src/Telemetry/Client.php',
				'line'     => 42,
				'class'    => 'CodeLearner\\Divi5WooCommerceMCP\\Telemetry\\Client',
				'function' => 'send_error',
			),
			array(
				'file' => '/srv/www/wp-includes/http.php',
				'line' => 100,
			),
		);

		$clean = Sanitizer::sanitize_frames( $frames, '/srv/www/wp-content/plugins/mcp/' );

		self::assertCount( 1, $clean );
		self::assertSame( 'src/Telemetry/Client.php', $clean[0]['file'] );
		self::assertSame( 42, $clean[0]['line'] );
		self::assertStringNotContainsString( '/srv/www', $clean[0]['file'] );
	}
}
