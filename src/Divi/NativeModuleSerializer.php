<?php
/**
 * Serialize runtime-native Divi 5 module trees to WordPress block comments.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

use InvalidArgumentException;
use RuntimeException;

final class NativeModuleSerializer {
	/**
	 * Serialize one native module for insertion under an existing parent.
	 *
	 * The caller is responsible for confirming that every module is registered
	 * on the active Divi runtime. This class still validates names, shape and
	 * parent/child compatibility so malformed trees cannot be serialized.
	 *
	 * @param array<string, mixed> $node Native module node.
	 */
	public static function to_block( array $node, string $parent_block_name ): string {
		return self::serialize_node( $node, $parent_block_name );
	}

	/**
	 * @param array<string, mixed> $node Native module node.
	 */
	private static function serialize_node( array $node, string $parent_block_name ): string {
		self::assert_node_shape( $node );

		$module_name = (string) $node['module_name'];

		if ( ! self::is_valid_module_name( $module_name ) ) {
			throw new InvalidArgumentException( 'Native module_name must identify a semantic divi/* block.' );
		}

		if ( ! ModuleRegistry::allows_child( $parent_block_name, $module_name ) ) {
			throw new InvalidArgumentException( 'Invalid Divi native module parent/child relationship.' );
		}

		$attributes = isset( $node['attributes'] ) ? $node['attributes'] : array();
		$children   = isset( $node['children'] ) ? $node['children'] : array();

		if ( ! is_array( $attributes ) ) {
			throw new InvalidArgumentException( 'Native module attributes must be an object.' );
		}

		if ( ! is_array( $children ) ) {
			throw new InvalidArgumentException( 'Native module children must be an array.' );
		}

		$comment = '<!-- wp:' . $module_name . self::serialize_attributes( $attributes );

		if ( array() === $children ) {
			return $comment . ' /-->';
		}

		$serialized_children = array();

		foreach ( $children as $child ) {
			if ( ! is_array( $child ) ) {
				throw new InvalidArgumentException( 'Each native child module must be an object.' );
			}

			$serialized_children[] = self::serialize_node( $child, $module_name );
		}

		return $comment . " -->\n"
			. implode( "\n", $serialized_children )
			. "\n<!-- /wp:" . $module_name . ' -->';
	}

	/**
	 * @param array<string, mixed> $node Native module node.
	 */
	private static function assert_node_shape( array $node ): void {
		$allowed_keys = array( 'module_name', 'attributes', 'children' );
		$unknown_keys = array_diff( array_keys( $node ), $allowed_keys );

		if ( array() !== $unknown_keys ) {
			throw new InvalidArgumentException( 'Native module nodes contain unsupported properties.' );
		}

		if ( ! isset( $node['module_name'] ) || ! is_string( $node['module_name'] ) ) {
			throw new InvalidArgumentException( 'Native module nodes require module_name.' );
		}
	}

	private static function is_valid_module_name( string $module_name ): bool {
		return 1 === preg_match( '/^divi\/[a-z0-9-]+$/', $module_name )
			&& LayoutManager::is_semantic_native_block_name( $module_name );
	}

	/**
	 * @param array<string, mixed> $attributes Native block attributes.
	 */
	private static function serialize_attributes( array $attributes ): string {
		if ( array() === $attributes ) {
			return '';
		}

		$json = wp_json_encode(
			$attributes,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);

		if ( false === $json ) {
			throw new RuntimeException( 'Native Divi module attributes could not be encoded.' );
		}

		return ' ' . $json;
	}

	private function __construct() {
	}
}
