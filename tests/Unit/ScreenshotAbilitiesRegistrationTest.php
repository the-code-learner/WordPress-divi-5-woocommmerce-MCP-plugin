<?php
/**
 * Screenshot diagnostics ability registration tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace {
	if ( ! function_exists( '__' ) ) {
		function __( string $text, string $domain = 'default' ): string {
			return $text;
		}
	}

	if ( ! function_exists( 'wp_register_ability' ) ) {
		/** @param array<string, mixed> $definition Ability definition. */
		function wp_register_ability( string $name, array $definition ): void {
			$GLOBALS['divi5_test_screenshot_abilities'][ $name ] = $definition;
		}
	}
}

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit {
	use CodeLearner\Divi5WooCommerceMCP\Screenshot\ScreenshotAbilities;
	use PHPUnit\Framework\TestCase;

	final class ScreenshotAbilitiesRegistrationTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['divi5_test_screenshot_abilities'] = array();
			ScreenshotAbilities::register_abilities();
		}

		public function test_registers_parameterless_readonly_status_ability(): void {
			$ability = $GLOBALS['divi5_test_screenshot_abilities']['divi5-woocommerce-mcp/divi-screenshot-status'];

			self::assertArrayNotHasKey( 'input_schema', $ability );
			self::assertTrue( $ability['meta']['mcp']['public'] );
			self::assertTrue( $ability['meta']['annotations']['readonly'] );
			self::assertFalse( $ability['meta']['annotations']['destructive'] );
			self::assertTrue( $ability['meta']['annotations']['idempotent'] );
			self::assertArrayHasKey( 'ready', $ability['output_schema']['properties'] );
			self::assertArrayHasKey( 'cdp_available', $ability['output_schema']['properties'] );
			self::assertArrayHasKey( 'smoke_test', $ability['output_schema']['properties'] );
		}
	}
}
