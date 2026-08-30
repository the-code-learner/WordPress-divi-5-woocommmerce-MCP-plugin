<?php
/**
 * Runtime-native Divi module ability registration tests.
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
		/**
		 * @param array<string, mixed> $definition Ability definition.
		 */
		function wp_register_ability( string $name, array $definition ): void {
			$GLOBALS['divi5_test_registered_abilities'][ $name ] = $definition;
		}
	}
}

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit {
	use CodeLearner\Divi5WooCommerceMCP\Divi\NativeModuleAbilities;
	use PHPUnit\Framework\TestCase;

	final class NativeModuleAbilitiesRegistrationTest extends TestCase {
		public function test_registers_runtime_native_insertion_ability(): void {
			$GLOBALS['divi5_test_registered_abilities'] = array();
			NativeModuleAbilities::register_abilities();

			$name = 'divi5-woocommerce-mcp/divi-insert-native-module';

			self::assertArrayHasKey( $name, $GLOBALS['divi5_test_registered_abilities'] );

			$definition = $GLOBALS['divi5_test_registered_abilities'][ $name ];
			$schema     = $definition['input_schema'];

			self::assertSame( array( 'post_id', 'parent_path', 'index', 'module' ), $schema['required'] );
			self::assertSame( '^divi/[a-z0-9-]+$', $schema['properties']['module']['properties']['module_name']['pattern'] );
			self::assertFalse( $schema['additionalProperties'] );
			self::assertFalse( $schema['properties']['module']['additionalProperties'] );
			self::assertTrue( $definition['meta']['mcp']['public'] );
			self::assertFalse( $definition['meta']['annotations']['readonly'] );
			self::assertTrue( $definition['meta']['annotations']['destructive'] );
			self::assertFalse( $definition['meta']['annotations']['idempotent'] );
		}
	}
}
