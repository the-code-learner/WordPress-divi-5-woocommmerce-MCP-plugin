<?php
/**
 * Clean-break document mutation engine tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Divi\BlockTreeEditor;
use CodeLearner\Divi5WooCommerceMCP\Divi\DocumentModel;
use CodeLearner\Divi5WooCommerceMCP\Divi\DocumentMutationEngine;
use PHPUnit\Framework\TestCase;

final class DocumentMutationEngineTest extends TestCase {
	public function test_created_handle_survives_structural_shift_and_can_be_targeted_later_in_batch(): void {
		$token         = str_repeat( 'a', 64 );
		$column_handle = DocumentModel::snapshot_handle( $token, 3, 'divi/column' );
		$text_handle   = DocumentModel::snapshot_handle( $token, 4, 'divi/text' );
		$operations    = array(
			array(
				'op'     => 'delete',
				'handle' => $text_handle,
			),
			array(
				'op'     => 'insert',
				'parent' => $column_handle,
				'index'  => 0,
				'node'   => array(
					'module_type' => 'divi/text',
					'new_handle'  => 'hero-copy',
					'properties'  => array(
						'content.innerContent' => 'Inserted copy',
					),
				),
			),
			array(
				'op'       => 'set',
				'handle'   => 'hero-copy',
				'property' => 'content.innerContent',
				'value'    => 'Updated copy',
			),
		);

		$plan = DocumentMutationEngine::plan( $this->blocks(), $token, $operations, $this->descriptors() );
		$text = BlockTreeEditor::get( $plan['blocks'], '0.0.0.0.0' );

		self::assertTrue( $plan['valid'] );
		self::assertSame( array(), $plan['errors'] );
		self::assertContains( 'hero-copy', $plan['created'] );
		self::assertSame( 'Updated copy', $text['attrs']['content']['innerContent']['desktop']['value'] );
	}

	public function test_invalid_operation_makes_entire_plan_invalid_with_machine_readable_error(): void {
		$token       = str_repeat( 'b', 64 );
		$text_handle = DocumentModel::snapshot_handle( $token, 4, 'divi/text' );
		$operations  = array(
			array(
				'op'       => 'set',
				'handle'   => $text_handle,
				'property' => 'content.innerContent',
				'value'    => 'Valid first edit',
			),
			array(
				'op'       => 'set',
				'handle'   => $text_handle,
				'property' => 'content.unknown',
				'value'    => 'Rejected edit',
			),
		);

		$plan  = DocumentMutationEngine::plan( $this->blocks(), $token, $operations, $this->descriptors() );
		$error = $plan['errors'][0];

		self::assertFalse( $plan['valid'] );
		self::assertSame( 'property_not_in_runtime_schema', $error['code'] );
		self::assertSame( 1, $error['operation_index'] );
		self::assertSame( $text_handle, $error['node'] );
		self::assertSame( 'content.unknown', $error['property'] );
		self::assertSame( 'Rejected edit', $error['offending_value'] );
		self::assertNotEmpty( $error['expected'] );
	}

	public function test_responsive_override_uses_runtime_proven_device_value_shape(): void {
		$token       = str_repeat( 'c', 64 );
		$text_handle = DocumentModel::snapshot_handle( $token, 4, 'divi/text' );
		$operations  = array(
			array(
				'op'       => 'responsive',
				'handle'   => $text_handle,
				'property' => 'content.innerContent',
				'device'   => 'tablet',
				'value'    => 'Tablet copy',
			),
		);

		$plan = DocumentMutationEngine::plan( $this->blocks(), $token, $operations, $this->descriptors() );
		$text = BlockTreeEditor::get( $plan['blocks'], '0.0.0.0.0' );

		self::assertTrue( $plan['valid'] );
		self::assertSame( 'Tablet copy', $text['attrs']['content']['innerContent']['tablet']['value'] );
	}

	public function test_state_and_preset_do_not_guess_native_paths(): void {
		$token       = str_repeat( 'd', 64 );
		$text_handle = DocumentModel::snapshot_handle( $token, 4, 'divi/text' );
		$operations  = array(
			array(
				'op'       => 'state',
				'handle'   => $text_handle,
				'property' => 'content.innerContent',
				'state'    => 'hover',
				'value'    => 'Hover copy',
			),
			array(
				'op'     => 'preset',
				'handle' => $text_handle,
				'preset' => 'example',
			),
		);

		$plan = DocumentMutationEngine::plan( $this->blocks(), $token, $operations, $this->descriptors() );

		self::assertFalse( $plan['valid'] );
		self::assertSame( 'state_mapping_unavailable', $plan['errors'][0]['code'] );
		self::assertSame( 'preset_mapping_unavailable', $plan['errors'][1]['code'] );
	}

	public function test_third_party_module_can_be_inserted_under_column_without_vendor_catalog(): void {
		$token         = str_repeat( 'e', 64 );
		$column_handle = DocumentModel::snapshot_handle( $token, 3, 'divi/column' );
		$operations    = array(
			array(
				'op'     => 'insert',
				'parent' => $column_handle,
				'index'  => 1,
				'node'   => array(
					'module_type' => 'acme/super-card',
					'new_handle'  => 'third-party-card',
					'properties'  => array(
						'content.innerContent' => 'Vendor-neutral fixture',
					),
				),
			),
		);

		$plan = DocumentMutationEngine::plan( $this->blocks(), $token, $operations, $this->descriptors() );
		$card = BlockTreeEditor::get( $plan['blocks'], '0.0.0.0.1' );

		self::assertTrue( $plan['valid'] );
		self::assertSame( 'acme/super-card', $card['blockName'] );
		self::assertSame( 'Vendor-neutral fixture', $card['attrs']['content']['innerContent']['desktop']['value'] );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function blocks(): array {
		return array(
			$this->block(
				'divi/placeholder',
				array(
					$this->block(
						'divi/section',
						array(
							$this->block(
								'divi/row',
								array(
									$this->block(
										'divi/column',
										array(
											$this->block(
												'divi/text',
												array(),
												array(
													'content' => array(
														'innerContent' => array(
															'desktop' => array( 'value' => 'Original copy' ),
														),
													),
												)
											),
										)
									),
								)
							),
						)
					),
				)
			),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $children Child blocks.
	 * @param array<string, mixed>             $attrs Attributes.
	 * @return array<string, mixed>
	 */
	private function block( string $name, array $children = array(), array $attrs = array() ): array {
		$slots = array( "\n" );

		foreach ( $children as $child ) {
			$slots[] = null;
			$slots[] = "\n";
		}

		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => $children,
			'innerHTML'    => str_repeat( "\n", count( $children ) + 1 ),
			'innerContent' => $slots,
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function descriptors(): array {
		$text_parameter = array(
			'semantic_path'     => 'content.innerContent',
			'native_path'       => 'content.innerContent',
			'native_provenance' => 'divi_runtime_settings',
			'type'              => 'text',
			'default'           => array(
				'desktop' => array( 'value' => '' ),
				'tablet'  => array( 'value' => '' ),
				'phone'   => array( 'value' => '' ),
			),
			'enum'              => array(),
			'constraints'       => array(),
			'allowed_units'     => array(),
			'responsive'        => 'supported',
			'devices'           => array( 'desktop', 'tablet', 'phone' ),
			'breakpoints'       => array( 'desktop', 'tablet', 'phone' ),
			'hover'             => 'supported',
			'sticky'            => 'unknown',
			'preset_support'    => 'supported',
			'design_variable'   => 'unknown',
			'global_value_support' => 'unknown',
		);

		return array(
			'divi/section'    => $this->descriptor( 'divi/section' ),
			'divi/row'        => $this->descriptor( 'divi/row' ),
			'divi/column'     => $this->descriptor( 'divi/column' ),
			'divi/text'       => $this->descriptor( 'divi/text', array( $text_parameter ) ),
			'acme/super-card' => $this->descriptor( 'acme/super-card', array( $text_parameter ) ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $parameters Parameters.
	 * @return array<string, mixed>
	 */
	private function descriptor( string $name, array $parameters = array() ): array {
		return array(
			'name'               => $name,
			'parameters'         => $parameters,
			'parent'             => array(),
			'ancestor'           => array(),
			'allowed_children'   => array(),
			'compatibility_mode' => 0 === strpos( $name, 'divi/' ) ? 'native' : 'unknown',
		);
	}
}
