<?php
/**
 * Native Divi block tree structural editor tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Divi\BlockTreeEditor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BlockTreeEditorTest extends TestCase {
	public function test_inserts_and_rebuilds_wordpress_child_slots(): void {
		$blocks = BlockTreeEditor::insert( $this->fixture(), '0.0.0.0', 1, $this->leaf( 'Inserted' ) );
		$column = BlockTreeEditor::get( $blocks, '0.0.0.0' );

		self::assertNotNull( $column );
		self::assertSame( 'Inserted', $column['innerBlocks'][1]['attrs']['label'] );
		self::assertSame( array( "\n", null, "\n", null, "\n", null, "\n", null, "\n" ), $column['innerContent'] );
	}

	public function test_reorders_with_final_index_semantics(): void {
		$blocks = BlockTreeEditor::move( $this->fixture(), '0.0.0.0.0', '0.0.0.0', 2 );
		$column = BlockTreeEditor::get( $blocks, '0.0.0.0' );

		self::assertNotNull( $column );
		self::assertSame(
			array( 'Second', 'Third', 'First' ),
			array_map(
				static function ( array $block ): string {
					return (string) $block['attrs']['label'];
				},
				$column['innerBlocks']
			)
		);
	}

	public function test_duplicates_nested_module_without_sharing_mutable_state(): void {
		$blocks = BlockTreeEditor::duplicate( $this->fixture(), '0.0.0', '0.0', 1 );
		$first  = BlockTreeEditor::get( $blocks, '0.0.0' );
		$copy   = BlockTreeEditor::get( $blocks, '0.0.1' );

		self::assertSame( $first, $copy );
		$copy['attrs']['changed'] = true;
		self::assertArrayNotHasKey( 'changed', $first['attrs'] );
	}

	public function test_deletes_module_and_reindexes_siblings(): void {
		$blocks = BlockTreeEditor::delete( $this->fixture(), '0.0.0.0.1' );
		$column = BlockTreeEditor::get( $blocks, '0.0.0.0' );

		self::assertNotNull( $column );
		self::assertCount( 2, $column['innerBlocks'] );
		self::assertSame( 'Third', $column['innerBlocks'][1]['attrs']['label'] );
	}

	public function test_rejects_move_into_descendant(): void {
		$this->expectException( InvalidArgumentException::class );
		BlockTreeEditor::move( $this->fixture(), '0.0', '0.0.0.0', 0 );
	}

	public function test_rejects_duplicate_into_descendant(): void {
		$this->expectException( InvalidArgumentException::class );
		BlockTreeEditor::duplicate( $this->fixture(), '0.0', '0.0.0.0', 0 );
	}

	public function test_rejects_out_of_range_destination(): void {
		$this->expectException( InvalidArgumentException::class );
		BlockTreeEditor::insert( $this->fixture(), '0.0.0.0', 99, $this->leaf( 'Nope' ) );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function fixture(): array {
		$column = $this->container(
			'divi/column',
			array(
				$this->leaf( 'First' ),
				$this->leaf( 'Second' ),
				$this->leaf( 'Third' ),
			)
		);

		return array(
			$this->container(
				'divi/placeholder',
				array(
					$this->container(
						'divi/section',
						array( $this->container( 'divi/row', array( $column ) ) )
					),
				)
			),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $children Child blocks.
	 * @return array<string, mixed>
	 */
	private function container( string $name, array $children ): array {
		return array(
			'blockName'    => $name,
			'attrs'        => array(),
			'innerBlocks'  => $children,
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function leaf( string $label ): array {
		return array(
			'blockName'    => 'divi/text',
			'attrs'        => array( 'label' => $label ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}
}
