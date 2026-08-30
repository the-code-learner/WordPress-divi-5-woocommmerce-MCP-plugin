<?php
/**
 * Runtime-native Divi module serializer tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace {
	if ( ! function_exists( 'wp_json_encode' ) ) {
		/**
		 * Minimal WordPress JSON encoder stand-in for isolated unit tests.
		 *
		 * @param mixed $value Value to encode.
		 * @return string|false
		 */
		function wp_json_encode( $value, int $flags = 0, int $depth = 512 ) {
			return json_encode( $value, $flags, $depth );
		}
	}
}

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit {
	use CodeLearner\Divi5WooCommerceMCP\Divi\NativeModuleSerializer;
	use InvalidArgumentException;
	use PHPUnit\Framework\TestCase;

	final class NativeModuleSerializerTest extends TestCase {
		public function test_serializes_verified_nested_runtime_module_tree(): void {
			$block = NativeModuleSerializer::to_block(
				array(
					'module_name' => 'divi/accordion',
					'attributes'  => array(
						'module' => array(
							'meta' => array(
								'adminLabel' => array(
									'desktop' => array( 'value' => 'FAQ' ),
								),
							),
						),
					),
					'children'    => array(
						array(
							'module_name' => 'divi/accordion-item',
							'attributes'  => array(
								'title' => array(
									'innerContent' => array(
										'desktop' => array( 'value' => 'Question' ),
									),
								),
							),
						),
					),
				),
				'divi/column'
			);

			self::assertStringContainsString( '<!-- wp:divi/accordion ', $block );
			self::assertStringContainsString( '<!-- wp:divi/accordion-item ', $block );
			self::assertStringContainsString( '<!-- /wp:divi/accordion -->', $block );
			self::assertStringContainsString( '"adminLabel":{"desktop":{"value":"FAQ"}}', $block );
			self::assertStringContainsString( '"value":"Question"', $block );
		}

		public function test_rejects_invalid_nested_runtime_relationship(): void {
			$this->expectException( InvalidArgumentException::class );

			NativeModuleSerializer::to_block(
				array(
					'module_name' => 'divi/accordion',
					'children'    => array(
						array( 'module_name' => 'divi/tab' ),
					),
				),
				'divi/column'
			);
		}

		public function test_rejects_arbitrary_non_divi_block_names(): void {
			$this->expectException( InvalidArgumentException::class );

			NativeModuleSerializer::to_block(
				array( 'module_name' => 'core/html' ),
				'divi/column'
			);
		}

		public function test_rejects_unknown_native_node_properties(): void {
			$this->expectException( InvalidArgumentException::class );

			NativeModuleSerializer::to_block(
				array(
					'module_name' => 'divi/accordion',
					'raw_html'    => '<script>alert(1)</script>',
				),
				'divi/column'
			);
		}
	}
}
