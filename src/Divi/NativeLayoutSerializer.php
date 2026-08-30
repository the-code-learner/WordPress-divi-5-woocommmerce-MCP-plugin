<?php
/**
 * Serialize the constrained semantic layout directly to native Divi 5 blocks.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

use InvalidArgumentException;
use RuntimeException;

final class NativeLayoutSerializer {
	private const MODULES = array(
		'section' => 'divi/section',
		'row'     => 'divi/row',
		'column'  => 'divi/column',
		'text'    => 'divi/text',
		'button'  => 'divi/button',
		'image'   => 'divi/image',
		'code'    => 'divi/code',
		'divider' => 'divi/divider',
	);

	private const CONTAINERS = array(
		'section' => array( 'row' ),
		'row'     => array( 'column' ),
		'column'  => array( 'text', 'button', 'image', 'code', 'divider' ),
	);

	/**
	 * Convert a semantic layout to serialized native Divi 5 blocks.
	 *
	 * This is the deterministic fallback for runtimes where Divi's official
	 * converter is present but has not registered its core conversion outlines.
	 *
	 * @param array<int, array<string, mixed>> $layout Sanitized semantic layout.
	 */
	public static function to_blocks( array $layout ): string {
		if ( array() === $layout ) {
			throw new InvalidArgumentException( 'Layout must contain at least one section.' );
		}

		$serialized = array();

		foreach ( $layout as $node ) {
			if ( ! is_array( $node ) ) {
				throw new InvalidArgumentException( 'Each layout node must be an object.' );
			}

			if ( 'section' !== ( $node['type'] ?? null ) ) {
				throw new InvalidArgumentException( 'Top-level layout nodes must be sections.' );
			}

			$serialized[] = self::serialize_node( $node, null );
		}

		return implode( "\n", $serialized );
	}

	/**
	 * @param array<string, mixed> $node Semantic node.
	 * @param string|null          $parent_type Parent semantic type.
	 */
	private static function serialize_node( array $node, ?string $parent_type ): string {
		$type = $node['type'] ?? null;

		if ( ! is_string( $type ) || ! isset( self::MODULES[ $type ] ) ) {
			throw new InvalidArgumentException( 'Unsupported Divi layout node type.' );
		}

		self::assert_parent_child_relationship( $parent_type, $type );

		$children = $node['children'] ?? array();

		if ( ! is_array( $children ) ) {
			throw new InvalidArgumentException( 'Divi node children must be an array.' );
		}

		$is_container = isset( self::CONTAINERS[ $type ] );

		if ( ! $is_container && array() !== $children ) {
			throw new InvalidArgumentException( 'Leaf Divi modules cannot contain child nodes.' );
		}

		$block_name = self::MODULES[ $type ];
		$attributes = self::block_attributes( $node, $type );
		$comment    = '<!-- wp:' . $block_name . self::serialize_attributes( $attributes );

		if ( ! $is_container ) {
			return $comment . ' /-->';
		}

		$serialized_children = array();

		foreach ( $children as $child ) {
			if ( ! is_array( $child ) ) {
				throw new InvalidArgumentException( 'Each child layout node must be an object.' );
			}

			$serialized_children[] = self::serialize_node( $child, $type );
		}

		return $comment . ' -->\n'
			. implode( "\n", $serialized_children )
			. "\n<!-- /wp:" . $block_name . ' -->';
	}

	/**
	 * @param array<string, mixed> $node Semantic node.
	 * @return array<string, mixed>
	 */
	private static function block_attributes( array $node, string $type ): array {
		$semantic_attributes = $node['attributes'] ?? array();

		if ( ! is_array( $semantic_attributes ) ) {
			throw new InvalidArgumentException( 'Divi node attributes must be an object.' );
		}

		$attributes = array();
		$label      = isset( $node['label'] ) && is_string( $node['label'] ) ? trim( $node['label'] ) : '';

		if ( '' === $label && isset( $semantic_attributes['admin_label'] ) && is_scalar( $semantic_attributes['admin_label'] ) ) {
			$label = trim( (string) $semantic_attributes['admin_label'] );
		}

		unset( $semantic_attributes['admin_label'] );

		if ( '' !== $label ) {
			$attributes['module']['meta']['adminLabel']['desktop']['value'] = $label;
		}

		$content = $node['content'] ?? '';

		if ( ! is_string( $content ) ) {
			throw new InvalidArgumentException( 'Divi node content must be a string.' );
		}

		if ( in_array( $type, array( 'text', 'code' ), true ) ) {
			$attributes['content']['innerContent']['desktop']['value'] = $content;
		} elseif ( 'button' === $type ) {
			$text = '' !== $content ? $content : self::take_scalar_attribute( $semantic_attributes, 'button_text' );
			$url  = self::take_scalar_attribute( $semantic_attributes, 'button_url' );

			$attributes['button']['innerContent']['desktop']['value']['text'] = $text;

			if ( '' !== $url ) {
				$attributes['button']['innerContent']['desktop']['value']['linkUrl'] = $url;
			}

			$link_target = self::take_scalar_attribute( $semantic_attributes, 'url_new_window' );

			if ( in_array( $link_target, array( 'on', 'yes', 'true', '1', '_blank' ), true ) ) {
				$attributes['button']['innerContent']['desktop']['value']['linkTarget'] = '_blank';
			}
		} elseif ( 'image' === $type ) {
			$src = self::take_scalar_attribute( $semantic_attributes, 'src' );

			if ( '' !== $src ) {
				$attributes['image']['innerContent']['desktop']['value']['src'] = $src;
			}

			$alt = self::take_scalar_attribute( $semantic_attributes, 'alt' );

			if ( '' !== $alt ) {
				$attributes['image']['innerContent']['desktop']['value']['alt'] = $alt;
			}
		} elseif ( 'column' === $type ) {
			$column_type = self::take_scalar_attribute( $semantic_attributes, 'type' );
			$attributes['module']['advanced']['type']['desktop']['value'] = '' !== $column_type ? $column_type : '4_4';
		}

		if ( array() !== $semantic_attributes ) {
			foreach ( $semantic_attributes as $key => $value ) {
				if ( ! is_string( $key ) || ! is_scalar( $value ) ) {
					throw new InvalidArgumentException( 'Native fallback attributes must have scalar values.' );
				}

				$attributes['unknownAttributes'][ $key ] = (string) $value;
			}
		}

		return $attributes;
	}

	/**
	 * @param array<string, mixed> $attributes Semantic attributes.
	 */
	private static function take_scalar_attribute( array &$attributes, string $key ): string {
		if ( ! isset( $attributes[ $key ] ) || ! is_scalar( $attributes[ $key ] ) ) {
			return '';
		}

		$value = (string) $attributes[ $key ];
		unset( $attributes[ $key ] );

		return $value;
	}

	private static function assert_parent_child_relationship( ?string $parent_type, string $type ): void {
		if ( null === $parent_type ) {
			if ( 'section' !== $type ) {
				throw new InvalidArgumentException( 'Only sections can be top-level nodes.' );
			}

			return;
		}

		$allowed = self::CONTAINERS[ $parent_type ] ?? array();

		if ( ! in_array( $type, $allowed, true ) ) {
			throw new InvalidArgumentException( 'Invalid Divi layout parent/child relationship.' );
		}
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
			throw new RuntimeException( 'Native Divi block attributes could not be encoded.' );
		}

		return ' ' . $json;
	}

	private function __construct() {
	}
}
