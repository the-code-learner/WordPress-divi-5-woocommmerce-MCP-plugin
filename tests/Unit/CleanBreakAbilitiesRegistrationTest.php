<?php
/**
 * Clean-break ability registration tests.
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
	use CodeLearner\Divi5WooCommerceMCP\Divi\CleanBreakAbilities;
	use PHPUnit\Framework\TestCase;

	final class CleanBreakAbilitiesRegistrationTest extends TestCase {
		/**
		 * @var array<string, array<string, mixed>>
		 */
		private array $abilities = array();

		protected function setUp(): void {
			$GLOBALS['divi5_test_registered_abilities'] = array();
			CleanBreakAbilities::register_abilities();
			$this->abilities = $GLOBALS['divi5_test_registered_abilities'];
		}

		public function test_registers_small_clean_break_read_surface(): void {
			$expected = array(
				'divi5-woocommerce-mcp/divi-runtime-describe',
				'divi5-woocommerce-mcp/divi-module-describe',
				'divi5-woocommerce-mcp/divi-document-get',
			);

			foreach ( $expected as $ability_name ) {
				self::assertArrayHasKey( $ability_name, $this->abilities );
				self::assertTrue( $this->abilities[ $ability_name ]['meta']['mcp']['public'] );
				self::assertTrue( $this->abilities[ $ability_name ]['meta']['annotations']['readonly'] );
				self::assertFalse( $this->abilities[ $ability_name ]['meta']['annotations']['destructive'] );
			}
		}

		public function test_runtime_descriptor_keeps_parameterless_adapter_contract(): void {
			self::assertArrayNotHasKey( 'input_schema', $this->abilities['divi5-woocommerce-mcp/divi-runtime-describe'] );
		}

		public function test_module_descriptor_accepts_third_party_block_namespaces(): void {
			$schema = $this->abilities['divi5-woocommerce-mcp/divi-module-describe']['input_schema'];
			$pattern = $schema['properties']['module_name']['pattern'];

			self::assertSame( '^[a-z0-9-]+/[a-z0-9-]+$', $pattern );
			self::assertSame( 1, preg_match( '/' . $pattern . '/', 'acme/super-card' ) );
			self::assertSame( 1, preg_match( '/' . $pattern . '/', 'pixel-fixture/feature-box' ) );
		}

		public function test_document_get_supports_opt_in_raw_native_data(): void {
			$schema = $this->abilities['divi5-woocommerce-mcp/divi-document-get']['input_schema'];

			self::assertSame( array( 'post_id' ), $schema['required'] );
			self::assertSame( 'boolean', $schema['properties']['include_native']['type'] );
			self::assertFalse( $schema['properties']['include_native']['default'] );
			self::assertFalse( $schema['additionalProperties'] );
		}
	}
}
