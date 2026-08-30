<?php
/**
 * Version tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Version;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase {
	public function test_version_is_semver(): void {
		self::assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', Version::NUMBER );
	}

	public function test_current_version(): void {
		self::assertSame( '0.2.2', Version::NUMBER );
	}
}
