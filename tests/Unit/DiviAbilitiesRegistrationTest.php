<?php
/**
 * Divi structural ability registration tests.
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
	use CodeLearner\Divi5WooCommerceMCP\Divi\Abilities;
	use PHPUnit\Framework\TestCase;

	final class DiviAbilitiesRegistrationTest extends TestCase {
		/**
		 * @var array<string, array<string, mixed>>
		 */
		private array $abilities = array();

		protected function setUp(): void {
			$GLOBALS['divi5_test_registered_abilities'] = array();
			Abilities::register_abilities();
			$this->abilities = $GLOBALS['divi5_test_registered_abilities'];
		}

		public function test_registers_complete_structural_editing_surface(): void {
			$expected = array(
				'divi5-woocommerce-mcp/divi-list-modules',
				'divi5-woocommerce-mcp/divi-get-module-schema',
				'divi5-woocommerce-mcp/divi-insert-module',
				'divi5-woocommerce-mcp/divi-delete-module',
				'divi5-woocommerce-mcp/divi-move-module',
				'divi5-woocommerce-mcp/divi-duplicate-module',
			);

			foreach ( $expected as $ability_name ) {
				self::assertArrayHasKey( $ability_name, $this->abilities );
				self::assertTrue( $this->abilities[ $ability_name ]['meta']['mcp']['public'] );
			}
		}

		public function test_insert_schema_allows_only_constrained_semantic_types(): void {
			$schema = $this->abilities['divi5-woocommerce-mcp/divi-insert-module']['input_schema'];
			$types  = $schema['properties']['module']['properties']['type']['enum'];

			self::assertSame(
				array( 'section', 'row', 'column', 'text', 'button', 'image', 'code', 'divider' ),
				$types
			);
			self::assertFalse( $schema['additionalProperties'] );
			self::assertFalse( $schema['properties']['module']['additionalProperties'] );
		}

		public function test_relocation_schema_requires_inspected_paths_and_final_index(): void {
			$schema = $this->abilities['divi5-woocommerce-mcp/divi-move-module']['input_schema'];

			self::assertSame( array( 'post_id', 'path', 'parent_path', 'index' ), $schema['required'] );
			self::assertSame( '^\\d+(?:\\.\\d+)*$', $schema['properties']['path']['pattern'] );
			self::assertSame( 0, $schema['properties']['index']['minimum'] );
		}

		public function test_parameterless_module_catalog_accepts_wordpress_null_input(): void {
			$result = Abilities::list_modules( null );

			self::assertTrue( $result['success'] );
			self::assertSame( 0, $result['module_count'] );
		}
	}
}
