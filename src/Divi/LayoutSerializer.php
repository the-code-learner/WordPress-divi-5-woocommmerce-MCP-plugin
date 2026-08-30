<?php
/**
 * Serialize a constrained semantic layout into Divi shortcodes.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

use InvalidArgumentException;

final class LayoutSerializer {
	private const MODULES = array(
		'section' => 'et_pb_section',
		'row'     => 'et_pb_row',
		'column'  => 'et_pb_column',
		'text'    => 'et_pb_text',
		'button'  => 'et_pb_button',
		'image'   => 'et_pb_image',
		'code'    => 'et_pb_code',
		'divider' => 'et_pb_divider',
	);

	private const CONTAINERS = array(
		'section' => array( 'row' ),
		'row'     => array( 'column' ),
		'column'  => array( 'text', 'button', 'image', 'code', 'divider' ),
	);

	/**
	 * Convert a semantic layout to Divi shortcode markup.
	 *
	 * @param array<int, array<string, mixed>> $layout Layout nodes.
	 */
	public static function to_shortcode( array $layout ): string {
		if ( array() === $layout ) {
			throw new InvalidArgumentException( 'Layout must contain at least one section.' );
		}

		$output = '';

		foreach ( $layout as $node ) {
			if ( ! is_array( $node ) ) {
				throw new InvalidArgumentException( 'Each layout node must be an object.' );
			}

			if ( 'section' !== ( $node['type'] ?? null ) ) {
				throw new InvalidArgumentException( 'Top-level layout nodes must be sections.' );
			}

			$output .= self::serialize_node( $node, null );
		}

		return $output;
	}

	/**
	 * Return supported semantic node types.
	 *
	 * @return array<int, string>
	 */
	public static function supported_types(): array {
		return array_keys( self::MODULES );
	}

	/**
	 * @param array<string, mixed> $node Node definition.
	 * @param string|null          $parent Parent semantic type.
	 */
	private static function serialize_node( array $node, ?string $parent ): string {
		$type = $node['type'] ?? null;

		if ( ! is_string( $type ) || ! isset( self::MODULES[ $type ] ) ) {
			throw new InvalidArgumentException( 'Unsupported Divi layout node type.' );
		}

		self::assert_parent_child_relationship( $parent, $type );

		$attributes = $node['attributes'] ?? array();

		if ( ! is_array( $attributes ) ) {
			throw new InvalidArgumentException( sprintf( 'Attributes for %s must be an object.', $type ) );
		}

		if ( isset( $node['label'] ) && is_string( $node['label'] ) && '' !== trim( $node['label'] ) && ! isset( $attributes['admin_label'] ) ) {
			$attributes['admin_label'] = $node['label'];
		}

		$content = $node['content'] ?? '';

		if ( ! is_string( $content ) ) {
			throw new InvalidArgumentException( sprintf( 'Content for %s must be a string.', $type ) );
		}

		if ( 'button' === $type && '' !== $content && ! isset( $attributes['button_text'] ) ) {
			$attributes['button_text'] = $content;
			$content                   = '';
		}

		if ( 'column' === $type && ! isset( $attributes['type'] ) ) {
			$attributes['type'] = '4_4';
		}

		$children = $node['children'] ?? array();

		if ( ! is_array( $children ) ) {
			throw new InvalidArgumentException( sprintf( 'Children for %s must be an array.', $type ) );
		}

		if ( ! isset( self::CONTAINERS[ $type ] ) && array() !== $children ) {
			throw new InvalidArgumentException( sprintf( 'The %s module cannot contain child nodes.', $type ) );
		}

		$children_markup = '';

		foreach ( $children as $child ) {
			if ( ! is_array( $child ) ) {
				throw new InvalidArgumentException( 'Each child layout node must be an object.' );
			}

			$children_markup .= self::serialize_node( $child, $type );
		}

		$shortcode = self::MODULES[ $type ];
		$opening   = '[' . $shortcode . self::serialize_attributes( $attributes ) . ']';

		return $opening . $content . $children_markup . '[/' . $shortcode . ']';
	}

	private static function assert_parent_child_relationship( ?string $parent, string $type ): void {
		if ( null === $parent ) {
			if ( 'section' !== $type ) {
				throw new InvalidArgumentException( 'Only sections can be top-level nodes.' );
			}

			return;
		}

		$allowed = self::CONTAINERS[ $parent ] ?? array();

		if ( ! in_array( $type, $allowed, true ) ) {
			throw new InvalidArgumentException( sprintf( 'A %s node cannot contain a %s node.', $parent, $type ) );
		}
	}

	/**
	 * @param array<string, mixed> $attributes Shortcode attributes.
	 */
	private static function serialize_attributes( array $attributes ): string {
		if ( array() === $attributes ) {
			return '';
		}

		ksort( $attributes );
		$serialized = '';

		foreach ( $attributes as $key => $value ) {
			if ( ! is_string( $key ) || 1 !== preg_match( '/^[A-Za-z][A-Za-z0-9_:-]*$/', $key ) ) {
				throw new InvalidArgumentException( 'Divi attribute names may contain only letters, numbers, underscores, colons, and hyphens.' );
			}

			if ( is_bool( $value ) ) {
				$value = $value ? 'on' : 'off';
			}

			if ( ! is_scalar( $value ) ) {
				throw new InvalidArgumentException( sprintf( 'Divi attribute %s must be a scalar value.', $key ) );
			}

			$serialized .= sprintf(
				' %s="%s"',
				$key,
				htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' )
			);
		}

		return $serialized;
	}

	private function __construct() {
	}
}
