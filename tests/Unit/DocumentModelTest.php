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
		$card        = $root['children'][0];

		self::assertSame( $root['handle'], $second[0]['handle'] );
		self::assertSame( $card['handle'], $second[0]['children'][0]['handle'] );
		self::assertNotSame( $root['handle'], $changed[0]['handle'] );
		self::assertSame( '0', $root['numeric_path'] );
		self::assertSame( '0.0', $card['numeric_path'] );
		self::assertSame( $root['handle'], $card['parent_handle'] );
		self::assertSame( 'document_snapshot', $card['handle_scope'] );
	}

	public function test_ast_normalizes_properties_and_keeps_raw_native_data_opt_in(): void {
		$blocks      = $this->fixture_blocks();
		$descriptors = $this->fixture_descriptors();
		$normalized  = DocumentModel::build_ast( $blocks, 'snapshot-a', false, $descriptors );
		$raw         = DocumentModel::build_ast( $blocks, 'snapshot-a', true, $descriptors );
		$card        = $normalized[0]['children'][0];
		$raw_card    = $raw[0]['children'][0];

		self::assertSame( 'module', $card['kind'] );
		self::assertSame( 'acme/super-card', $card['module_type'] );
		self::assertSame( 'Fixture copy', $card['normalized_properties']['content.innerContent']['desktop']['value'] );
		self::assertSame( 'acme', $card['provider']['id'] );
		self::assertArrayNotHasKey( 'native', $card );
		self::assertSame( 'acme/super-card', $raw_card['native']['block_name'] );
		self::assertSame( 'Fixture copy', $raw_card['native']['raw_attributes']['content']['innerContent']['desktop']['value'] );
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
						'blockName'   => 'acme/super-card',
						'attrs'       => array(
							'content' => array(
								'innerContent' => array(
									'desktop' => array( 'value' => 'Fixture copy' ),
								),
							),
						),
						'innerBlocks' => array(),
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
			'acme/super-card' => array(
				'name'               => 'acme/super-card',
				'provider'           => array(
					'id'         => 'acme',
					'provenance' => 'block_namespace',
				),
				'provenance'         => array( 'source' => 'wp_block_type_registry' ),
				'compatibility_mode' => 'unknown',
				'parent'             => array(),
				'ancestor'           => array(),
				'allowed_children'   => array(),
				'capabilities'       => array( 'responsive' => 'supported' ),
				'parameters'         => array(
					array(
						'semantic_path' => 'content.innerContent',
					),
				),
			),
		);
	}
}
