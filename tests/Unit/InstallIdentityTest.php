<?php
/**
 * Telemetry installation identity tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Telemetry\InstallIdentity;
use PHPUnit\Framework\TestCase;

final class InstallIdentityTest extends TestCase {
	public function test_generate_returns_random_local_identifier(): void {
		$first  = InstallIdentity::generate();
		$second = InstallIdentity::generate();

		self::assertTrue( InstallIdentity::is_valid( $first ) );
		self::assertTrue( InstallIdentity::is_valid( $second ) );
		self::assertNotSame( $first, $second );
		self::assertSame( 32, strlen( $first ) );
	}
}
