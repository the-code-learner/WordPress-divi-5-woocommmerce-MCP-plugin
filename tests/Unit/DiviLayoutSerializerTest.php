<?php
/**
 * Divi semantic layout serializer tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Divi\LayoutSerializer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DiviLayoutSerializerTest extends TestCase {
	public function test_supported_types_cover_initial_native_authoring_surface(): void {
		self::assertSame(
			array( 'section', 'row', 'column', 'text', 'button', 'image', 'code', 'divider' ),
			LayoutSerializer::supported_types()
		);
	}

	public function test_semantic_layout_becomes_valid_divi_shortcode_hierarchy(): void {
		$markup = LayoutSerializer::to_shortcode(
			array(
				array(
					'type'     => 'section',
					'label'    => 'Hero',
					'children' => array(
						array(
							'type'     => 'row',
							'children' => array(
								array(
									'type'     => 'column',
									'children' => array(
										array(
											'type'    => 'text',
											'content' => '<h1>Velocity</h1>',
										),
										array(
											'type'       => 'button',
											'content'    => 'Start',
											'attributes' => array( 'button_url' => '#start' ),
										),
									),
								),
							),
					),
				),
			)
		);

		self::assertStringContainsString( '[et_pb_section admin_label="Hero"]', $markup );
		self::assertStringContainsString( '[et_pb_row]', $markup );
		self::assertStringContainsString( '[et_pb_column type="4_4"]', $markup );
		self::assertStringContainsString( '[et_pb_text]<h1>Velocity</h1>[/et_pb_text]', $markup );
		self::assertStringContainsString( '[et_pb_button button_text="Start" button_url="#start"][/et_pb_button]', $markup );
	}

	public function test_invalid_hierarchy_is_rejected_before_divi_conversion(): void {
		$this->expectException( InvalidArgumentException::class );

		LayoutSerializer::to_shortcode(
			array(
				array(
					'type'     => 'section',
					'children' => array(
						array( 'type' => 'text' ),
					),
				),
			)
		);
	}

	public function test_attribute_values_are_escaped(): void {
		$markup = LayoutSerializer::to_shortcode(
			array(
				array(
					'type'     => 'section',
					'children' => array(
						array(
							'type'     => 'row',
							'children' => array(
								array(
									'type'     => 'column',
									'children' => array(
										array(
											'type'       => 'text',
											'attributes' => array( 'admin_label' => 'A "quoted" label' ),
										),
									),
								),
							),
					),
				),
			)
		);

		self::assertStringContainsString( 'admin_label="A &quot;quoted&quot; label"', $markup );
	}
}
