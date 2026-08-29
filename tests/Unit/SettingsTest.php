<?php
/**
 * Settings tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Admin\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {
	public function test_github_updates_setting_accepts_enabled_values(): void {
		self::assertTrue( Settings::sanitize_github_updates_enabled( '1' ) );
		self::assertTrue( Settings::sanitize_github_updates_enabled( true ) );
	}

	public function test_github_updates_setting_accepts_disabled_values(): void {
		self::assertFalse( Settings::sanitize_github_updates_enabled( '0' ) );
		self::assertFalse( Settings::sanitize_github_updates_enabled( false ) );
		self::assertFalse( Settings::sanitize_github_updates_enabled( null ) );
	}

	public function test_telemetry_settings_default_sanitizers_support_opt_out(): void {
		self::assertTrue( Settings::sanitize_usage_telemetry_enabled( '1' ) );
		self::assertFalse( Settings::sanitize_usage_telemetry_enabled( '0' ) );
		self::assertTrue( Settings::sanitize_error_reporting_enabled( true ) );
		self::assertFalse( Settings::sanitize_error_reporting_enabled( false ) );
	}
}
