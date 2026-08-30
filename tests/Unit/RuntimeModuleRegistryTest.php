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
		$parameters = array();

		foreach ( $descriptor['parameters'] as $parameter ) {
			$parameters[ $parameter['semantic_path'] ] = $parameter;
		}

		self::assertArrayHasKey( 'content.innerContent', $parameters );
		self::assertSame( 'divi_runtime_settings', $parameters['content.innerContent']['native_provenance'] );
		self::assertSame( 'text', $parameters['content.innerContent']['type'] );
		self::assertSame( 'supported', $parameters['content.innerContent']['responsive'] );
		self::assertContains( 'desktop', $parameters['content.innerContent']['breakpoints'] );
		self::assertSame( 'unavailable', $parameters['content.innerContent']['sticky'] );
		self::assertSame( 'supported', $parameters['content.innerContent']['preset_support'] );
		self::assertSame( array( 'acme/super-card-item' ), $descriptor['allowed_children'] );
		self::assertArrayHasKey( 'raw_runtime', $descriptor );
		self::assertArrayHasKey( 'attributes', $descriptor['raw_runtime'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function divi_attributes(): array {
		return array(
			'module'   => array( 'type' => 'object' ),
			'content'  => array(
				'type'     => 'object',
				'default'  => array(
					'innerContent' => array(
						'desktop' => array( 'value' => 'Fixture copy' ),
					),
				),
				'settings' => array(
					'innerContent' => array(
						'item' => array(
							'attrName'  => 'content.innerContent',
							'features'  => array(
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
			'lock'     => array( 'type' => 'object' ),
			'metadata' => array( 'type' => 'object' ),
			'className' => array( 'type' => 'string' ),
			'style'    => array( 'type' => 'object' ),
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
