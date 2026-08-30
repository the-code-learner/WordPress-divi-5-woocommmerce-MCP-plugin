<?php
/**
 * Safe structural edits for parsed WordPress block trees.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

use InvalidArgumentException;

final class BlockTreeEditor {
	/**
	 * Return a block at a path.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<string, mixed>|null
	 */
	public static function get( array $blocks, string $path ): ?array {
		$indexes = self::parse_path( $path );
		$current = $blocks;
		$block   = null;

		foreach ( $indexes as $index ) {
			if ( ! isset( $current[ $index ] ) || ! is_array( $current[ $index ] ) ) {
				return null;
			}

			$block   = $current[ $index ];
			$current = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] )
				? $block['innerBlocks']
				: array();
		}

		return $block;
	}

	/**
	 * Insert a parsed block into a container.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param array<string, mixed>             $block Block to insert.
	 * @return array<int, array<string, mixed>>
	 */
	public static function insert( array $blocks, string $parent_path, int $index, array $block ): array {
		$parent = &self::get_reference( $blocks, self::parse_path( $parent_path ) );

		if ( null === $parent ) {
			throw new InvalidArgumentException( 'Destination parent path does not exist.' );
		}

		$children = isset( $parent['innerBlocks'] ) && is_array( $parent['innerBlocks'] )
			? $parent['innerBlocks']
			: array();

		if ( $index < 0 || $index > count( $children ) ) {
			throw new InvalidArgumentException( 'Destination index is outside the parent child range.' );
		}

		array_splice( $children, $index, 0, array( $block ) );
		$parent['innerBlocks'] = array_values( $children );
		self::sync_child_slots( $parent );

		return $blocks;
	}

	/**
	 * Delete a block and return the updated tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array<string, mixed>>
	 */
	public static function delete( array $blocks, string $path ): array {
		$indexes = self::parse_path( $path );

		if ( count( $indexes ) < 2 ) {
			throw new InvalidArgumentException( 'The root Divi placeholder cannot be deleted.' );
		}

		$index  = (int) array_pop( $indexes );
		$parent = &self::get_reference( $blocks, $indexes );

		if ( null === $parent || ! isset( $parent['innerBlocks'][ $index ] ) ) {
			throw new InvalidArgumentException( 'Source module path does not exist.' );
		}

		array_splice( $parent['innerBlocks'], $index, 1 );
		$parent['innerBlocks'] = array_values( $parent['innerBlocks'] );
		self::sync_child_slots( $parent );

		return $blocks;
	}

	/**
	 * Move a block to a destination container and final child index.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array<string, mixed>>
	 */
	public static function move( array $blocks, string $path, string $parent_path, int $index ): array {
		if ( $parent_path === $path || 0 === strpos( $parent_path, $path . '.' ) ) {
			throw new InvalidArgumentException( 'A module cannot be moved inside itself or one of its descendants.' );
		}

		$block = self::get( $blocks, $path );

		if ( null === $block ) {
			throw new InvalidArgumentException( 'Source module path does not exist.' );
		}

		$source_parent_path = self::parent_path( $path );
		$blocks             = self::delete( $blocks, $path );

		// The destination index is the final index after removal. This makes
		// same-parent reordering deterministic in both directions.
		if ( $source_parent_path === $parent_path ) {
			$parent = self::get( $blocks, $parent_path );
			$count  = null === $parent || ! isset( $parent['innerBlocks'] ) || ! is_array( $parent['innerBlocks'] )
				? 0
				: count( $parent['innerBlocks'] );

			$index = min( $index, $count );
		}

		return self::insert( $blocks, $parent_path, $index, $block );
	}

	/**
	 * Duplicate a block to a destination container.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array<string, mixed>>
	 */
	public static function duplicate( array $blocks, string $path, string $parent_path, int $index ): array {
		if ( $parent_path === $path || 0 === strpos( $parent_path, $path . '.' ) ) {
			throw new InvalidArgumentException( 'A module cannot be duplicated inside itself or one of its descendants.' );
		}

		$block = self::get( $blocks, $path );

		if ( null === $block ) {
			throw new InvalidArgumentException( 'Source module path does not exist.' );
		}

		return self::insert( $blocks, $parent_path, $index, $block );
	}

	public static function parent_path( string $path ): string {
		$indexes = self::parse_path( $path );

		if ( count( $indexes ) < 2 ) {
			throw new InvalidArgumentException( 'Root paths do not have a parent block.' );
		}

		array_pop( $indexes );

		return implode( '.', $indexes );
	}

	/**
	 * @return array<int, int>
	 */
	private static function parse_path( string $path ): array {
		if ( 1 !== preg_match( '/^\d+(?:\.\d+)*$/', $path ) ) {
			throw new InvalidArgumentException( 'Module path must be a dot-separated list of numeric block indexes.' );
		}

		return array_map( 'intval', explode( '.', $path ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param array<int, int>                  $indexes Path indexes.
	 * @return array<string, mixed>|null
	 */
	private static function &get_reference( array &$blocks, array $indexes ): ?array {
		$null  = null;
		$index = array_shift( $indexes );

		if ( null === $index || ! isset( $blocks[ $index ] ) || ! is_array( $blocks[ $index ] ) ) {
			return $null;
		}

		if ( array() === $indexes ) {
			return $blocks[ $index ];
		}

		if ( ! isset( $blocks[ $index ]['innerBlocks'] ) || ! is_array( $blocks[ $index ]['innerBlocks'] ) ) {
			return $null;
		}

		return self::get_reference( $blocks[ $index ]['innerBlocks'], $indexes );
	}

	/**
	 * Rebuild the child placeholders consumed by serialize_block(). Divi parent
	 * blocks are dynamic comments, so their only meaningful inner content is the
	 * ordered sequence of child block slots.
	 *
	 * @param array<string, mixed> $container Parent block.
	 */
	private static function sync_child_slots( array &$container ): void {
		$slots = array( "\n" );

		foreach ( $container['innerBlocks'] as $unused ) {
			$slots[] = null;
			$slots[] = "\n";
		}

		$container['innerContent'] = $slots;
		$container['innerHTML']    = str_repeat( "\n", count( $container['innerBlocks'] ) + 1 );
	}

	private function __construct() {
	}
}
