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

	public function test_generic_custom_attribute_inserts_divi_record_shape(): void {
		$token  = str_repeat( '2', 64 );
		$handle = DocumentModel::snapshot_handle( $token, 0, 'divi/text' );
		$plan   = RuntimeNativeWriter::plan(
			$this->blocks(),
			$token,
			array(
				array(
					'op'     => 'attribute',
					'handle' => $handle,
					'name'   => 'title',
					'value'  => 'Accessible title',
				),
			),
			$this->descriptors(),
			array( 'desktop' )
		);

		self::assertTrue( $plan['valid'] );
		self::assertSame(
			array(
				array(
					'name'          => 'title',
					'value'         => 'Accessible title',
					'targetElement' => '',
				),
			),
			$plan['blocks'][0]['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes']
		);
		self::assertSame( 'module.decoration.attributes.desktop.value.attributes', $plan['operations'][0]['native_path'] );
	}

	public function test_generic_custom_attribute_updates_by_identity_and_preserves_unrelated_records(): void {
		$token  = str_repeat( '3', 64 );
		$handle = DocumentModel::snapshot_handle( $token, 0, 'divi/text' );
		$plan   = RuntimeNativeWriter::plan(
			$this->blocks_with_custom_attributes(),
			$token,
			array(
				array(
					'op'     => 'attribute',
					'handle' => $handle,
					'name'   => 'title',
					'value'  => 'Updated title',
				),
			),
			$this->descriptors(),
			array( 'desktop' )
		);

		$records = $plan['blocks'][0]['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'];

		self::assertTrue( $plan['valid'] );
		self::assertCount( 2, $records );
		self::assertSame( 'data-existing', $records[0]['name'] );
		self::assertSame( 'keep', $records[0]['value'] );
		self::assertSame( 'title', $records[1]['name'] );
		self::assertSame( 'Updated title', $records[1]['value'] );
		self::assertSame( '', $records[1]['targetElement'] );
	}

	public function test_generic_custom_attribute_unset_removes_only_matching_identity(): void {
		$token  = str_repeat( '4', 64 );
		$handle = DocumentModel::snapshot_handle( $token, 0, 'divi/text' );
		$plan   = RuntimeNativeWriter::plan(
			$this->blocks_with_custom_attributes(),
			$token,
			array(
				array(
					'op'     => 'attribute',
					'handle' => $handle,
					'name'   => 'title',
				),
			),
			$this->descriptors(),
			array( 'desktop' )
		);

		$records = $plan['blocks'][0]['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'];

		self::assertTrue( $plan['valid'] );
		self::assertSame( 'unset', $plan['operations'][0]['op'] );
		self::assertSame(
			array(
				array(
					'name'          => 'data-existing',
					'value'         => 'keep',
					'targetElement' => '',
				),
			),
			$records
		);
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

	public function test_responsive_writer_requires_discovered_breakpoint_value_path(): void {
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

	public function test_responsive_writer_rejects_feature_without_exact_value_path(): void {
		$token       = str_repeat( '5', 64 );
		$handle      = DocumentModel::snapshot_handle( $token, 0, 'divi/text' );
		$descriptors = $this->descriptors();
		unset( $descriptors['divi/text']['parameter_graph'][0]['native_value_paths']['tabletWide'] );

		$plan = RuntimeNativeWriter::plan(
			$this->blocks(),
			$token,
			array(
				array(
					'op'         => 'responsive',
					'handle'     => $handle,
					'property'   => 'content.innerContent',
					'breakpoint' => 'tabletWide',
					'value'      => 'Unproven',
				),
			),
			$descriptors,
			array( 'desktop', 'tabletWide' )
		);

		self::assertFalse( $plan['valid'] );
		self::assertSame( 'native_mapping_unavailable', $plan['errors'][0]['code'] );
	}

	public function test_state_writer_rejects_feature_only_evidence_without_native_mapping(): void {
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

		self::assertFalse( $plan['valid'] );
		self::assertSame( 'native_mapping_unavailable', $plan['errors'][0]['code'] );
	}

	public function test_state_writer_accepts_explicit_runtime_proven_native_mapping(): void {
		$token       = str_repeat( '6', 64 );
		$handle      = DocumentModel::snapshot_handle( $token, 0, 'divi/text' );
		$descriptors = $this->descriptors();
		$descriptors['divi/text']['parameter_graph'][0]['native_state_paths'] = array(
			'desktop' => array(
				'hover' => 'content.innerContent.desktop.hover',
			),
		);
		$plan = RuntimeNativeWriter::plan(
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
			$descriptors,
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

	/** @return array<int, array<string, mixed>> */
	private function blocks_with_custom_attributes(): array {
		$blocks = $this->blocks();
		$blocks[0]['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] = array(
			array(
				'name'          => 'data-existing',
				'value'         => 'keep',
				'targetElement' => '',
			),
			array(
				'name'          => 'title',
				'value'         => 'Original title',
				'targetElement' => '',
			),
		);

		return $blocks;
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
						'semantic_path'     => 'content.innerContent',
						'runtime_hint'      => 'content.innerContent',
						'native_path'       => 'content.innerContent',
						'native_value_paths' => array(
							'desktop'    => 'content.innerContent.desktop.value',
							'tabletWide' => 'content.innerContent.tabletWide.value',
							'tablet'     => 'content.innerContent.tablet.value',
							'phone'      => 'content.innerContent.phone.value',
						),
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
