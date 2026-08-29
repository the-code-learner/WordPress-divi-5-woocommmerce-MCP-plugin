<?php
/**
 * Telemetry scheduling and ownership tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Telemetry\Telemetry;
use PHPUnit\Framework\TestCase;

final class TelemetryTest extends TestCase {
	public function test_first_heartbeat_is_delayed_between_one_and_three_days(): void {
		self::assertSame( 86400, Telemetry::first_delay_seconds( 1 ) );
		self::assertSame( 172800, Telemetry::first_delay_seconds( 172800 ) );
		self::assertSame( 259200, Telemetry::first_delay_seconds( 999999 ) );
	}

	public function test_weekly_schedule_has_bounded_twelve_hour_jitter(): void {
		self::assertSame( 561600, Telemetry::next_delay_seconds( -999999 ) );
		self::assertSame( 604800, Telemetry::next_delay_seconds( 0 ) );
		self::assertSame( 648000, Telemetry::next_delay_seconds( 999999 ) );
	}

	public function test_error_capture_is_limited_to_plugin_files(): void {
		self::assertTrue(
			Telemetry::is_plugin_owned_file(
				'/srv/www/wp-content/plugins/mcp/src/Telemetry/Client.php',
				'/srv/www/wp-content/plugins/mcp/'
			)
		);
		self::assertFalse(
			Telemetry::is_plugin_owned_file(
				'/srv/www/wp-content/plugins/other/plugin.php',
				'/srv/www/wp-content/plugins/mcp/'
			)
		);
	}
}
