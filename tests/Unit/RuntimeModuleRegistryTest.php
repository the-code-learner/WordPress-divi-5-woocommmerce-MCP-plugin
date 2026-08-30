<?php
/**
 * Clean-break runtime module discovery tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Divi\RuntimeModuleRegistry;
use PHPUnit\Framework\TestCase;
use stdClass;

final class RuntimeModuleRegistryTest extends TestCase {
	public function test_discovers_third_party_runtime_modules_without_vendor_catalogs(): void {
		$registered = array(
			'divi/text'                 => $this->block_type( 'Text', 'module', $this->divi_attributes() ),
			'acme/super-card'           => $this->block_type( 'Super Card', 'module', $this->divi_attributes(), array( 'acme/super-card-item' ) ),
			'pixel-fixture/feature-box' => $this->block_type( 'Feature Box', 'module', $this->divi_attributes() ),
			'core/paragraph'            => $this->block_type( 'Paragraph', 'text', array( 'content' => array( 'type' => 'string' ) ) ),
		);
		$registered['acme/super-card']->divi_compatibility_mode = 'native';

		$catalog = RuntimeModuleRegistry::catalog_from_types( $registered );
		$names   = array_column( $catalog['modules'], 'name' );

		self::assertTrue( $catalog['success'] );
		self::assertSame( 3, $catalog['module_count'] );
		self::assertContains( 'divi/text', $names );
		self::assertContains( 'acme/super-card', $names );
		self::assertContains( 'pixel-fixture/feature-box', $names );
		self::assertNotContains( 'core/paragraph', $names );
	}

	public function test_reports_provider_and_compatibility_only_from_runtime_evidence(): void {
		$registered = array(
			'acme/super-card'           => $this->block_type( 'Super Card', 'module', $this->divi_attributes() ),
			'pixel-fixture/feature-box' => $this->block_type( 'Feature Box', 'module', $this->divi_attributes() ),
		);
		$registered['acme/super-card']->compatibility_mode = 'native';

		$native  = RuntimeModuleRegistry::describe_from_types( 'acme/super-card', $registered );
		$unknown = RuntimeModuleRegistry::describe_from_types( 'pixel-fixture/feature-box', $registered );

		self::assertSame( 'acme', $native['provider']['id'] );
		self::assertSame( 'block_namespace', $native['provider']['provenance'] );
		self::assertSame( 'native', $native['compatibility_mode'] );
		self::assertSame( 'unknown', $unknown['compatibility_mode'] );
	}

	public function test_normalizes_runtime_parameter_graph_and_preserves_raw_schema(): void {
		$registered = array(
			'acme/super-card' => $this->block_type(
				'Super Card',
				'module',
				$this->divi_attributes(),
				array( 'acme/super-card-item' )
			),
		);
		$descriptor = RuntimeModuleRegistry::describe_from_types( 'acme/super-card', $registered );
		$parameters = $this->parameter_map( $descriptor['parameter_graph'] );
		$authoring  = $this->parameter_map( $descriptor['parameters'] );

		self::assertArrayHasKey( 'content.innerContent', $parameters );
		self::assertSame( 'runtime_attr_name', $parameters['content.innerContent']['native_provenance'] );
		self::assertSame( 'high', $parameters['content.innerContent']['native_confidence'] );
		self::assertSame( 'content.innerContent', $parameters['content.innerContent']['native_path'] );
		self::assertSame( 'content.innerContent.desktop.value', $parameters['content.innerContent']['native_value_paths']['desktop'] );
		self::assertSame( 'text', $parameters['content.innerContent']['type'] );
		self::assertSame( 'Fixture copy', $parameters['content.innerContent']['default'] );
		self::assertSame( 'Fixture copy', $parameters['content.innerContent']['default_by_device']['desktop'] );
		self::assertSame( 'unknown', $parameters['content.innerContent']['responsive'] );
		self::assertSame( 'unavailable', $parameters['content.innerContent']['sticky'] );
		self::assertSame( 'supported', $parameters['content.innerContent']['preset_support'] );
		self::assertSame( 'supported', $parameters['content.innerContent']['write_mapping'] );
		self::assertArrayHasKey( 'content.innerContent', $authoring );
		self::assertSame( 'Fixture copy', $authoring['content.innerContent']['default']['desktop']['value'] );
		self::assertSame( array( 'acme/super-card-item' ), $descriptor['allowed_children'] );
		self::assertArrayHasKey( 'raw_runtime', $descriptor );
		self::assertArrayHasKey( 'attributes', $descriptor['raw_runtime'] );
	}

	public function test_live_image_controls_keep_semantic_and_native_paths_separate(): void {
		$registered = array(
			'divi/image' => $this->block_type( 'Image', 'module', $this->image_attributes() ),
		);
		$descriptor = RuntimeModuleRegistry::describe_from_types( 'divi/image', $registered );
		$parameters = $this->parameter_map( $descriptor['parameter_graph'] );
		$authoring  = $this->parameter_map( $descriptor['parameters'] );

		$src = $parameters['image.innerContent.src'];
		self::assertNull( $src['native_path'] );
		self::assertSame( 'unknown', $src['native_provenance'] );
		self::assertSame( 'unavailable', $src['write_mapping'] );
		self::assertSame( 'supported', $src['responsive'] );
		self::assertSame( 'supported', $src['hover'] );
		self::assertSame( 'unavailable', $src['sticky'] );
		self::assertSame( 'supported', $src['preset_support'] );
		self::assertArrayNotHasKey( 'image.innerContent.src', $authoring );

		$link_target = $parameters['image.innerContent.linkTarget'];
		self::assertSame( 'image.innerContent.linkTarget', $link_target['native_path'] );
		self::assertSame( 'default_leaf_exact_match', $link_target['native_provenance'] );
		self::assertSame( 'medium', $link_target['native_confidence'] );
		self::assertSame( 'image.innerContent.desktop.value.linkTarget', $link_target['native_value_paths']['desktop'] );
		self::assertSame( array( 'off', 'on' ), $link_target['enum'] );
		self::assertSame( 'off', $link_target['default'] );
		self::assertSame( 'unavailable', $link_target['responsive'] );
		self::assertSame( 'unavailable', $link_target['hover'] );
		self::assertSame( 'unavailable', $link_target['sticky'] );
		self::assertArrayNotHasKey( 'image.innerContent.linkTarget', $authoring );

		$fullwidth = $parameters['module.advanced.sizing.forceFullwidth'];
		self::assertSame( 'module.advanced.sizing.forceFullwidth', $fullwidth['runtime_hint'] );
		self::assertSame( 'module.advanced.forceFullwidth', $fullwidth['native_path'] );
		self::assertSame( 'default_leaf_unique_match', $fullwidth['native_provenance'] );
		self::assertSame( 'medium', $fullwidth['native_confidence'] );
		self::assertSame( 'module.advanced.forceFullwidth.desktop.value', $fullwidth['native_value_paths']['desktop'] );
		self::assertSame( 'off', $fullwidth['default'] );
		self::assertArrayHasKey( 'module.advanced.sizing.forceFullwidth', $authoring );
		self::assertSame( 'off', $authoring['module.advanced.sizing.forceFullwidth']['default']['desktop']['value'] );
	}

	/**
	 * @param array<int, array<string, mixed>> $parameters Parameters.
	 * @return array<string, array<string, mixed>>
	 */
	private function parameter_map( array $parameters ): array {
		$map = array();

		foreach ( $parameters as $parameter ) {
			if ( isset( $parameter['semantic_path'] ) && is_string( $parameter['semantic_path'] ) ) {
				$map[ $parameter['semantic_path'] ] = $parameter;
			}
		}

		return $map;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function divi_attributes(): array {
		return array(
			'module'    => array( 'type' => 'object' ),
			'content'   => array(
				'type'     => 'object',
				'default'  => array(
					'innerContent' => array(
						'desktop' => array( 'value' => 'Fixture copy' ),
					),
				),
				'settings' => array(
					'innerContent' => array(
						'item' => array(
							'attrName' => 'content.innerContent',
							'features' => array(
								'dynamicContent' => array( 'type' => 'text' ),
								'sticky'         => false,
								'preset'         => 'content',
							),
							'component' => array(
								'name' => 'divi/richtext',
								'type' => 'field',
							),
						),
					),
				),
			),
			'lock'      => array( 'type' => 'object' ),
			'metadata'  => array( 'type' => 'object' ),
			'className' => array( 'type' => 'string' ),
			'style'     => array( 'type' => 'object' ),
		);
	}

	/**
	 * Reduced read-only live Divi 5 Image schema evidence.
	 *
	 * @return array<string, mixed>
	 */
	private function image_attributes(): array {
		return array(
			'module'    => array(
				'type'     => 'object',
				'default'  => array(
					'advanced' => array(
						'forceFullwidth' => array(
							'desktop' => array( 'value' => 'off' ),
						),
					),
				),
				'settings' => array(
					'advanced' => array(
						'sizing' => array(
							'groupType' => 'group-items',
							'items'     => array(
								'forceFullwidth' => array(
									'groupSlug' => 'designSizing',
									'attrName'  => 'module.advanced.sizing',
									'subName'   => 'forceFullwidth',
									'features'  => array(
										'hover'  => false,
										'sticky' => false,
									),
									'component' => array(
										'type' => 'field',
										'name' => 'divi/toggle',
									),
								),
							),
						),
					),
				),
			),
			'image'     => array(
				'type'     => 'object',
				'default'  => array(
					'innerContent' => array(
						'desktop' => array(
							'value' => array( 'linkTarget' => 'off' ),
						),
					),
				),
				'settings' => array(
					'innerContent' => array(
						'groupType' => 'group-items',
						'items'     => array(
							'src'        => array(
								'groupSlug' => 'contentImage',
								'subName'   => 'src',
								'features'  => array(
									'dynamicContent' => array( 'type' => 'image' ),
									'sticky'         => false,
									'responsive'     => true,
									'hover'          => true,
									'preset'         => 'content',
								),
								'component' => array(
									'type' => 'field',
									'name' => 'divi/upload',
								),
							),
							'linkTarget' => array(
								'groupSlug' => 'contentImageLink',
								'subName'   => 'linkTarget',
								'features'  => array(
									'responsive' => false,
									'hover'      => false,
									'sticky'     => false,
									'preset'     => 'content',
								),
								'component' => array(
									'type'  => 'field',
									'name'  => 'divi/select',
									'props' => array(
										'options' => array(
											'off' => array( 'label' => 'In The Current Tab' ),
											'on'  => array( 'label' => 'In A New Tab' ),
										),
									),
								),
							),
						),
					),
				),
			),
			'lock'      => array( 'type' => 'object' ),
			'metadata'  => array( 'type' => 'object' ),
			'className' => array( 'type' => 'string' ),
			'style'     => array( 'type' => 'object' ),
		);
	}

	/**
	 * @param array<string, mixed> $attributes Attributes.
	 * @param array<int, string>   $allowed_children Allowed child blocks.
	 */
	private function block_type( string $title, string $category, array $attributes, array $allowed_children = array() ): stdClass {
		$type                 = new stdClass();
		$type->title          = $title;
		$type->category       = $category;
		$type->attributes     = $attributes;
		$type->supports       = array();
		$type->allowed_blocks = $allowed_children;

		return $type;
	}
}
