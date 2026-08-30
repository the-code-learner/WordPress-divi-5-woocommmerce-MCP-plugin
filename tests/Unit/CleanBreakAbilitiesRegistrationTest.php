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

		public function test_registers_small_clean_break_surface(): void {
			$expected = array(
				'divi5-woocommerce-mcp/divi-runtime-describe',
				'divi5-woocommerce-mcp/divi-module-describe',
				'divi5-woocommerce-mcp/divi-document-get',
				'divi5-woocommerce-mcp/divi-document-validate',
				'divi5-woocommerce-mcp/divi-document-mutate',
			);

			foreach ( $expected as $ability_name ) {
				self::assertArrayHasKey( $ability_name, $this->abilities );
				self::assertTrue( $this->abilities[ $ability_name ]['meta']['mcp']['public'] );
			}
		}

		public function test_runtime_descriptor_keeps_parameterless_adapter_contract(): void {
			self::assertArrayNotHasKey( 'input_schema', $this->abilities['divi5-woocommerce-mcp/divi-runtime-describe'] );
		}

		public function test_module_descriptor_accepts_third_party_block_namespaces(): void {
			$schema  = $this->abilities['divi5-woocommerce-mcp/divi-module-describe']['input_schema'];
			$pattern = $schema['properties']['module_name']['pattern'];

			self::assertSame( '^[a-z0-9-]+/[a-z0-9-]+$', $pattern );
			self::assertSame( 1, preg_match( '~' . $pattern . '~', 'acme/super-card' ) );
			self::assertSame( 1, preg_match( '~' . $pattern . '~', 'pixel-fixture/feature-box' ) );
		}

		public function test_document_get_supports_opt_in_raw_native_data(): void {
			$schema = $this->abilities['divi5-woocommerce-mcp/divi-document-get']['input_schema'];

			self::assertSame( array( 'post_id' ), $schema['required'] );
			self::assertSame( 'boolean', $schema['properties']['include_native']['type'] );
			self::assertFalse( $schema['properties']['include_native']['default'] );
			self::assertFalse( $schema['additionalProperties'] );
		}

		public function test_validate_is_readonly_and_mutate_is_destructive(): void {
			$validate = $this->abilities['divi5-woocommerce-mcp/divi-document-validate'];
			$mutate   = $this->abilities['divi5-woocommerce-mcp/divi-document-mutate'];

			self::assertTrue( $validate['meta']['annotations']['readonly'] );
			self::assertFalse( $validate['meta']['annotations']['destructive'] );
			self::assertFalse( $mutate['meta']['annotations']['readonly'] );
			self::assertTrue( $mutate['meta']['annotations']['destructive'] );
			self::assertFalse( $mutate['meta']['annotations']['idempotent'] );
		}

		public function test_batch_contract_requires_snapshot_token_and_operations(): void {
			$schema = $this->abilities['divi5-woocommerce-mcp/divi-document-validate']['input_schema'];

			self::assertSame( array( 'post_id', 'document_token', 'operations' ), $schema['required'] );
			self::assertSame( '^[a-f0-9]{64}$', $schema['properties']['document_token']['pattern'] );
			self::assertSame( 100, $schema['properties']['operations']['maxItems'] );
			self::assertContains( 'responsive', $schema['properties']['operations']['items']['properties']['op']['enum'] );
			self::assertContains( 'preset', $schema['properties']['operations']['items']['properties']['op']['enum'] );
		}
	}
}
