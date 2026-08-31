<?php
/**
 * Generic runtime bridge ability registration tests.
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
			$GLOBALS['divi5_test_registered_abilities'][ $name ] = $definition;
		}
	}
}

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit {
	use CodeLearner\Divi5WooCommerceMCP\Divi\RuntimeBridgeAbilities;
	use PHPUnit\Framework\TestCase;

	final class RuntimeBridgeAbilitiesRegistrationTest extends TestCase {
		/** @var array<string, array<string, mixed>> */
		private array $abilities = array();

		protected function setUp(): void {
			$GLOBALS['divi5_test_registered_abilities'] = array();
			RuntimeBridgeAbilities::register_abilities();
			$this->abilities = $GLOBALS['divi5_test_registered_abilities'];
		}

		public function test_registers_generic_runtime_surface(): void {
			$expected = array(
				'divi5-woocommerce-mcp/divi-runtime-list-registries',
				'divi5-woocommerce-mcp/divi-runtime-describe-registry',
				'divi5-woocommerce-mcp/divi-document-native-validate',
				'divi5-woocommerce-mcp/divi-document-native-mutate',
				'divi5-woocommerce-mcp/divi-render',
			);

			foreach ( $expected as $ability_name ) {
				self::assertArrayHasKey( $ability_name, $this->abilities );
				self::assertTrue( $this->abilities[ $ability_name ]['meta']['mcp']['public'] );
			}
		}

		public function test_native_mutation_schema_exposes_generic_operations(): void {
			$schema = $this->abilities['divi5-woocommerce-mcp/divi-document-native-mutate']['input_schema'];
			$ops    = $schema['properties']['operations']['items']['properties']['op']['enum'];

			self::assertSame( array( 'post_id', 'document_token', 'operations' ), $schema['required'] );
			self::assertContains( 'set', $ops );
			self::assertContains( 'unset', $ops );
			self::assertContains( 'attribute', $ops );
			self::assertContains( 'responsive', $ops );
			self::assertContains( 'state', $ops );
			self::assertContains( 'preset', $ops );
		}

		public function test_native_validation_is_readonly_and_native_mutation_is_destructive(): void {
			$validate = $this->abilities['divi5-woocommerce-mcp/divi-document-native-validate'];
			$mutate   = $this->abilities['divi5-woocommerce-mcp/divi-document-native-mutate'];

			self::assertTrue( $validate['meta']['annotations']['readonly'] );
			self::assertFalse( $validate['meta']['annotations']['destructive'] );
			self::assertFalse( $mutate['meta']['annotations']['readonly'] );
			self::assertTrue( $mutate['meta']['annotations']['destructive'] );
		}
	}
}
