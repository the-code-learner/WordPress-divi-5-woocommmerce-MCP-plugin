<?php
/**
 * Native Divi layout serializer tests.
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
	use CodeLearner\Divi5WooCommerceMCP\Divi\LayoutManager;
	use CodeLearner\Divi5WooCommerceMCP\Divi\NativeLayoutSerializer;
	use InvalidArgumentException;
	use PHPUnit\Framework\TestCase;

	final class NativeLayoutSerializerTest extends TestCase {
		public function test_serializes_verified_native_section_row_column_text_and_button_schema(): void {
			$blocks = NativeLayoutSerializer::to_blocks(
				array(
					array(
						'type'     => 'section',
						'label'    => 'Hero',
						'children' => array(
							array(
								'type'     => 'row',
								'children' => array(
									array(
										'type'       => 'column',
										'attributes' => array( 'type' => '4_4' ),
										'children'   => array(
											array(
												'type'    => 'text',
												'content' => '<h1>Velocity Native</h1>',
											),
											array(
												'type'       => 'button',
												'content'    => 'Continua',
												'attributes' => array(
													'button_url'    => '#next',
													'module_class' => 'velocity-action',
												),
											),
										),
									),
								),
							),
						),
					),
				)
			);

			self::assertStringContainsString( '<!-- wp:divi/section ', $blocks );
			self::assertStringContainsString( '<!-- wp:divi/row -->', $blocks );
			self::assertStringContainsString( '<!-- wp:divi/column ', $blocks );
			self::assertStringContainsString( '<!-- wp:divi/text ', $blocks );
			self::assertStringContainsString( '<!-- wp:divi/button ', $blocks );
			self::assertStringContainsString( '"adminLabel":{"desktop":{"value":"Hero"}}', $blocks );
			self::assertStringContainsString( '"type":{"desktop":{"value":"4_4"}}', $blocks );
			self::assertStringContainsString( '"content":{"innerContent":{"desktop":{"value":"', $blocks );
			self::assertStringContainsString( 'Velocity Native', $blocks );
			self::assertStringNotContainsString( '<h1>Velocity Native</h1>', $blocks );
			self::assertStringContainsString( '"text":"Continua","linkUrl":"#next"', $blocks );
			self::assertStringContainsString( '"unknownAttributes":{"module_class":"velocity-action"}', $blocks );
			self::assertStringNotContainsString( 'divi/shortcode-module', $blocks );
		}

		public function test_rejects_invalid_native_hierarchy(): void {
			$this->expectException( InvalidArgumentException::class );

			NativeLayoutSerializer::to_blocks(
				array(
					array(
						'type'     => 'section',
						'children' => array(
							array(
								'type' => 'text',
							),
						),
					),
				)
			);
		}

		public function test_shortcode_fallback_is_not_counted_as_editable_native_module(): void {
			self::assertFalse( LayoutManager::is_semantic_native_block_name( 'divi/placeholder' ) );
			self::assertFalse( LayoutManager::is_semantic_native_block_name( 'divi/shortcode-module' ) );
			self::assertFalse( LayoutManager::is_semantic_native_block_name( 'core/paragraph' ) );
			self::assertTrue( LayoutManager::is_semantic_native_block_name( 'divi/section' ) );
			self::assertTrue( LayoutManager::is_semantic_native_block_name( 'divi/button' ) );
		}
	}
}
