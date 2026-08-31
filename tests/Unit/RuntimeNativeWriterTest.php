<?php
/**
 * Generic runtime native writer tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Divi\DocumentModel;
use CodeLearner\Divi5WooCommerceMCP\Divi\RuntimeNativeWriter;
use PHPUnit\Framework\TestCase;

final class RuntimeNativeWriterTest extends TestCase {
	public function test_semantic_class_attribute_maps_to_divi_custom_attributes_shape(): void {
		$token  = str_repeat( 'a', 64 );
		$handle = DocumentModel::snapshot_handle( $token, 0, 'divi/text' );
		$plan   = RuntimeNativeWriter::plan(
			$this->blocks(),
			$token,
			array(
				array(
					'op'     => 'attribute',
					'handle' => $handle,
					'name'   => 'class',
					'value'  => 'bc-hero-section',
				),
			),
			$this->descriptors(),
			array( 'desktop', 'tabletWide', 'tablet', 'phone' )
		);

		self::assertTrue( $plan['valid'] );
		self::assertSame( 'bc-hero-section', $plan['blocks'][0]['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] );
		self::assertSame( 'divi-custom-attributes-adapter', $plan['operations'][0]['validation_level'] );
	}

	public function test_schema_discovered_raw_native_path_can_be_written_without_authoring_mapping(): void {
		$token  = str_repeat( 'b', 64 );
		$handle = DocumentModel::snapshot_handle( $token, 0, 'divi/text' );
		$plan   = RuntimeNativeWriter::plan(
			$this->blocks(),
			$token,
			array(
				array(
					'op'          => 'set',
					'handle'      => $handle,
					'native_path' => 'module.decoration.superGlow.desktop.value',
					'value'       => array( 'strength' => 12 ),
				),
			),
			$this->descriptors(),
			array( 'desktop' )
		);

		self::assertTrue( $plan['valid'] );
		self::assertSame( array( 'strength' => 12 ), $plan['blocks'][0]['attrs']['module']['decoration']['superGlow']['desktop']['value'] );
		self::assertStringContainsString( 'module.decoration.superGlow', $plan['operations'][0]['path_evidence'] );
	}

	public function test_unknown_nested_native_path_is_rejected_even_beneath_object_root(): void {
		$token  = str_repeat( 'c', 64 );
		$handle = DocumentModel::snapshot_handle( $token, 0, 'divi/text' );
		$plan   = RuntimeNativeWriter::plan(
			$this->blocks(),
			$token,
			array(
				array(
					'op'          => 'set',
					'handle'      => $handle,
					'native_path' => 'module.foo.unproven.desktop.value',
					'value'       => 'nope',
				),
			),
			$this->descriptors(),
			array( 'desktop' )
		);

		self::assertFalse( $plan['valid'] );
		self::assertSame( 'native_path_not_runtime_proven', $plan['errors'][0]['code'] );
	}

	public function test_responsive_writer_uses_discovered_breakpoint_not_hardcoded_device_list(): void {
		$token  = str_repeat( 'd', 64 );
		$handle = DocumentModel::snapshot_handle( $token, 0, 'divi/text' );
		$plan   = RuntimeNativeWriter::plan(
			$this->blocks(),
			$token,
			array(
				array(
					'op'         => 'responsive',
					'handle'     => $handle,
					'property'   => 'content.innerContent',
					'breakpoint' => 'tabletWide',
					'value'      => 'Wide tablet copy',
				),
			),
			$this->descriptors(),
			array( 'desktop', 'tabletWide', 'tablet', 'phone' )
		);

		self::assertTrue( $plan['valid'] );
		self::assertSame( 'Wide tablet copy', $plan['blocks'][0]['attrs']['content']['innerContent']['tabletWide']['value'] );
	}

	public function test_state_writer_uses_runtime_parameter_feature_evidence(): void {
		$token  = str_repeat( 'e', 64 );
		$handle = DocumentModel::snapshot_handle( $token, 0, 'divi/text' );
		$plan   = RuntimeNativeWriter::plan(
			$this->blocks(),
			$token,
			array(
				array(
					'op'       => 'state',
					'handle'   => $handle,
					'property' => 'content.innerContent',
					'state'    => 'hover',
					'value'    => 'Hover copy',
				),
			),
			$this->descriptors(),
			array( 'desktop' )
		);

		self::assertTrue( $plan['valid'] );
		self::assertSame( 'Hover copy', $plan['blocks'][0]['attrs']['content']['innerContent']['desktop']['hover'] );
	}

	public function test_preset_application_uses_native_module_meta_path(): void {
		$token  = str_repeat( 'f', 64 );
		$handle = DocumentModel::snapshot_handle( $token, 0, 'divi/text' );
		$plan   = RuntimeNativeWriter::plan(
			$this->blocks(),
			$token,
			array(
				array(
					'op'        => 'preset',
					'handle'    => $handle,
					'preset_id' => 'default',
				),
			),
			$this->descriptors(),
			array( 'desktop' )
		);

		self::assertTrue( $plan['valid'] );
		self::assertSame( 'default', $plan['blocks'][0]['attrs']['module']['meta']['modulePreset'] );
	}

	public function test_event_handler_custom_attribute_is_rejected(): void {
		$token  = str_repeat( '1', 64 );
		$handle = DocumentModel::snapshot_handle( $token, 0, 'divi/text' );
		$plan   = RuntimeNativeWriter::plan(
			$this->blocks(),
			$token,
			array(
				array(
					'op'     => 'attribute',
					'handle' => $handle,
					'name'   => 'onclick',
					'value'  => 'alert(1)',
				),
			),
			$this->descriptors(),
			array( 'desktop' )
		);

		self::assertFalse( $plan['valid'] );
		self::assertSame( 'invalid_attribute_name', $plan['errors'][0]['code'] );
	}

	/** @return array<int, array<string, mixed>> */
	private function blocks(): array {
		return array(
			array(
				'blockName'    => 'divi/text',
				'attrs'        => array(
					'content' => array(
						'innerContent' => array(
							'desktop' => array( 'value' => 'Original' ),
						),
					),
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
		);
	}

	/** @return array<string, array<string, mixed>> */
	private function descriptors(): array {
		return array(
			'divi/text' => array(
				'name' => 'divi/text',
				'raw_runtime' => array(
					'attributes' => array(
						'module'    => array( 'type' => 'object' ),
						'content'   => array( 'type' => 'object' ),
						'className' => array( 'type' => 'string' ),
					),
				),
				'parameter_graph' => array(
					array(
						'semantic_path' => 'content.innerContent',
						'runtime_hint'  => 'content.innerContent',
						'native_path'   => 'content.innerContent',
						'type'          => 'text',
						'hover'         => 'supported',
						'sticky'        => 'unavailable',
						'enum'          => array(),
					),
					array(
						'semantic_path' => 'module.decoration.superGlow',
						'runtime_hint'  => 'module.decoration.superGlow',
						'native_path'   => null,
						'type'          => 'divi/super-glow',
						'hover'         => 'unknown',
						'sticky'        => 'unknown',
						'enum'          => array(),
					),
				),
			),
		);
	}
}
