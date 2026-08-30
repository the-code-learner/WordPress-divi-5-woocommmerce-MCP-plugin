<?php
/**
 * Normalized Divi document AST tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Divi\DocumentModel;
use PHPUnit\Framework\TestCase;

final class DocumentModelTest extends TestCase {
	public function test_snapshot_handles_are_stable_while_numeric_paths_remain_locators(): void {
		$blocks      = $this->fixture_blocks();
		$descriptors = $this->fixture_descriptors();
		$first       = DocumentModel::build_ast( $blocks, 'snapshot-a', false, $descriptors );
		$second      = DocumentModel::build_ast( $blocks, 'snapshot-a', false, $descriptors );
		$changed     = DocumentModel::build_ast( $blocks, 'snapshot-b', false, $descriptors );
		$root        = $first[0];
		$section     = $root['children'][0];

		self::assertSame( $root['handle'], $second[0]['handle'] );
		self::assertSame( $section['handle'], $second[0]['children'][0]['handle'] );
		self::assertNotSame( $root['handle'], $changed[0]['handle'] );
		self::assertSame( '0', $root['numeric_path'] );
		self::assertSame( '0.0', $section['numeric_path'] );
		self::assertSame( $root['handle'], $section['parent_handle'] );
		self::assertSame( 'document_snapshot', $section['handle_scope'] );
	}

	public function test_ast_normalizes_live_breakpoint_envelopes_and_keeps_raw_native_data_opt_in(): void {
		$blocks      = $this->fixture_blocks();
		$descriptors = $this->fixture_descriptors();
		$normalized  = DocumentModel::build_ast( $blocks, 'snapshot-a', false, $descriptors );
		$raw         = DocumentModel::build_ast( $blocks, 'snapshot-a', true, $descriptors );
		$section     = $normalized[0]['children'][0];
		$column      = $section['children'][0];
		$text        = $column['children'][0];
		$button      = $column['children'][1];
		$raw_text    = $raw[0]['children'][0]['children'][0]['children'][0];

		self::assertSame( 'Hero Velocity', $section['normalized_properties']['module.meta.adminLabel']['value'] );
		self::assertSame( '4_4', $column['normalized_properties']['module.advanced.type']['value'] );
		self::assertSame( 'Desktop copy', $text['normalized_properties']['content.innerContent']['value'] );
		self::assertSame( 'Tablet copy', $text['normalized_properties']['content.innerContent']['value_by_device']['tablet'] );
		self::assertSame( 'content.innerContent', $text['normalized_properties']['content.innerContent']['native_path'] );
		self::assertSame( 'content.innerContent.tablet.value', $text['normalized_properties']['content.innerContent']['native_value_paths']['tablet'] );
		self::assertSame( 'supported', $text['authoring']['clean_break_write'] );
		self::assertSame( 'Explore the framework', $button['normalized_properties']['button.innerContent.text']['value'] );
		self::assertSame( '#system', $button['normalized_properties']['button.innerContent.linkUrl']['value'] );
		self::assertArrayNotHasKey( 'native', $text );
		self::assertSame( 'Desktop copy', $raw_text['native']['raw_attributes']['content']['innerContent']['desktop']['value'] );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function fixture_blocks(): array {
		return array(
			array(
				'blockName'   => 'divi/placeholder',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'divi/section',
						'attrs'       => array(
							'module' => array(
								'meta' => array(
									'adminLabel' => array(
										'desktop' => array( 'value' => 'Hero Velocity' ),
									),
								),
							),
						),
						'innerBlocks' => array(
							array(
								'blockName'   => 'divi/column',
								'attrs'       => array(
									'module' => array(
										'advanced' => array(
											'type' => array(
												'desktop' => array( 'value' => '4_4' ),
											),
										),
									),
								),
								'innerBlocks' => array(
									array(
										'blockName'   => 'divi/text',
										'attrs'       => array(
											'content' => array(
												'innerContent' => array(
													'desktop' => array( 'value' => 'Desktop copy' ),
													'tablet'  => array( 'value' => 'Tablet copy' ),
												),
											),
										),
										'innerBlocks' => array(),
									),
									array(
										'blockName'   => 'divi/button',
										'attrs'       => array(
											'button' => array(
												'innerContent' => array(
													'desktop' => array(
														'value' => array(
															'text'    => 'Explore the framework',
															'linkUrl' => '#system',
														),
													),
												),
											),
										),
										'innerBlocks' => array(),
									),
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function fixture_descriptors(): array {
		return array(
			'divi/section' => $this->descriptor(
				'divi/section',
				array( $this->parameter( 'module.meta.adminLabel' ) )
			),
			'divi/column'  => $this->descriptor(
				'divi/column',
				array( $this->parameter( 'module.advanced.type' ) )
			),
			'divi/text'    => $this->descriptor(
				'divi/text',
				array( $this->parameter( 'content.innerContent' ) )
			),
			'divi/button'  => $this->descriptor(
				'divi/button',
				array(
					$this->parameter( 'button.innerContent.text' ),
					$this->parameter( 'button.innerContent.linkUrl' ),
				)
			),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $parameters Parameters.
	 * @return array<string, mixed>
	 */
	private function descriptor( string $name, array $parameters ): array {
		return array(
			'name'               => $name,
			'provider'           => array(
				'id'         => 'divi',
				'provenance' => 'block_namespace',
			),
			'provenance'         => array( 'source' => 'wp_block_type_registry' ),
			'compatibility_mode' => 'native',
			'parent'             => array(),
			'ancestor'           => array(),
			'allowed_children'   => array(),
			'capabilities'       => array(),
			'parameter_graph'    => $parameters,
			'parameters'         => array(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parameter( string $semantic_path ): array {
		return array(
			'semantic_path' => $semantic_path,
			'native_path'   => null,
		);
	}
}
