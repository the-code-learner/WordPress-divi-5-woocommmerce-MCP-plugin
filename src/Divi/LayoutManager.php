<?php
/**
 * Divi 5 native layout inspection and draft editing.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

use Throwable;

final class LayoutManager {
	private const CONVERSION_CLASS = '\\ET\\Builder\\Packages\\Conversion\\Conversion';

	public static function is_native_authoring_available(): bool {
		return Detector::is_available()
			&& class_exists( self::CONVERSION_CLASS )
			&& method_exists( self::CONVERSION_CLASS, 'maybeConvertContent' )
			&& function_exists( 'parse_blocks' )
			&& function_exists( 'serialize_blocks' );
	}

	/**
	 * Inspect the editable Divi block tree for a post.
	 *
	 * @return array<string, mixed>
	 */
	public static function inspect( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return self::failure( $post_id, 'post_not_found', 'The requested post does not exist.' );
		}

		$blocks        = parse_blocks( (string) $post->post_content );
		$native_count  = self::count_divi_blocks( $blocks );
		$source_format = 'html';

		if ( $native_count > 0 ) {
			$source_format = 'divi5-blocks';
		} elseif ( false !== strpos( (string) $post->post_content, '[et_pb_' ) ) {
			$source_format = 'divi4-shortcodes';
		}

		return array(
			'success'            => true,
			'post_id'            => $post_id,
			'post_status'        => (string) $post->post_status,
			'builder_enabled'    => 'on' === get_post_meta( $post_id, '_et_pb_use_builder', true ),
			'native_authoring'   => self::is_native_authoring_available(),
			'source_format'      => $source_format,
			'native_block_count' => $native_count,
			'layout'             => self::describe_blocks( $blocks ),
			'error_code'         => null,
			'error_message'      => null,
		);
	}

	/**
	 * Replace a draft post with a semantic layout converted by Divi's own D4-to-D5 converter.
	 *
	 * @param array<int, array<string, mixed>> $layout Semantic layout.
	 * @return array<string, mixed>
	 */
	public static function save_semantic_layout( int $post_id, array $layout ): array {
		if ( ! self::is_native_authoring_available() ) {
			return self::failure( $post_id, 'divi5_native_authoring_unavailable', 'Divi 5 native conversion APIs are not available on this site.' );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return self::failure( $post_id, 'post_not_found', 'The requested post does not exist.' );
		}

		if ( ! in_array( (string) $post->post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
			return self::failure( $post_id, 'draft_required', 'Divi layout writes are restricted to draft or pending content. Create or restore a draft before editing.' );
		}

		try {
			$sanitized_layout = self::sanitize_layout( $layout );
			$shortcodes       = LayoutSerializer::to_shortcode( $sanitized_layout );
			$conversion_class = self::CONVERSION_CLASS;
			$native_content   = $conversion_class::maybeConvertContent( $shortcodes, true, $post_id );
		} catch ( Throwable $throwable ) {
			return self::failure( $post_id, 'layout_conversion_failed', $throwable->getMessage() );
		}

		if ( ! is_string( $native_content ) || false === strpos( $native_content, '<!-- wp:divi/' ) ) {
			return self::failure( $post_id, 'layout_conversion_failed', 'Divi did not return native Divi 5 block content for this layout.' );
		}

		$native_content = self::ensure_placeholder_wrapper( $native_content );
		$revision_id    = self::save_revision( $post_id );
		$updated_id     = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => wp_slash( $native_content ),
			),
			true
		);

		if ( is_wp_error( $updated_id ) ) {
			return self::failure( $post_id, 'post_update_failed', $updated_id->get_error_message() );
		}

		update_post_meta( $post_id, '_et_pb_use_builder', 'on' );
		clean_post_cache( $post_id );

		$result                 = self::inspect( $post_id );
		$result['revision_id']  = $revision_id;
		$result['write_method'] = 'divi5-official-conversion';

		return $result;
	}

	/**
	 * Patch a native Divi 5 block's attributes by the path returned from inspect().
	 *
	 * @param array<string, mixed> $attribute_patch Attribute patch.
	 * @return array<string, mixed>
	 */
	public static function update_module_attributes( int $post_id, string $path, array $attribute_patch ): array {
		if ( ! self::is_native_authoring_available() ) {
			return self::failure( $post_id, 'divi5_native_authoring_unavailable', 'Divi 5 native block APIs are not available on this site.' );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return self::failure( $post_id, 'post_not_found', 'The requested post does not exist.' );
		}

		if ( ! in_array( (string) $post->post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
			return self::failure( $post_id, 'draft_required', 'Divi module writes are restricted to draft or pending content.' );
		}

		if ( 1 !== preg_match( '/^\d+(?:\.\d+)*$/', $path ) ) {
			return self::failure( $post_id, 'invalid_module_path', 'Module path must be a dot-separated list of numeric block indexes.' );
		}

		$blocks  = parse_blocks( (string) $post->post_content );
		$indexes = array_map( 'intval', explode( '.', $path ) );
		$block   = &self::block_by_path( $blocks, $indexes );

		if ( null === $block ) {
			return self::failure( $post_id, 'module_not_found', 'No block exists at the requested module path.' );
		}

		$block_name = $block['blockName'] ?? null;

		if ( ! is_string( $block_name ) || 0 !== strpos( $block_name, 'divi/' ) || 'divi/placeholder' === $block_name ) {
			return self::failure( $post_id, 'not_a_divi5_module', 'The requested path does not identify an editable native Divi 5 module.' );
		}

		$current_attributes = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$block['attrs']     = self::merge_attributes( $current_attributes, $attribute_patch );
		$serialized         = serialize_blocks( $blocks );
		$revision_id        = self::save_revision( $post_id );
		$updated_id         = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => wp_slash( $serialized ),
			),
			true
		);

		if ( is_wp_error( $updated_id ) ) {
			return self::failure( $post_id, 'post_update_failed', $updated_id->get_error_message() );
		}

		update_post_meta( $post_id, '_et_pb_use_builder', 'on' );
		clean_post_cache( $post_id );

		$result                  = self::inspect( $post_id );
		$result['revision_id']   = $revision_id;
		$result['updated_path']  = $path;
		$result['updated_block'] = $block_name;
		$result['write_method']  = 'native-block-attribute-patch';

		return $result;
	}

	/**
	 * @param array<int, mixed> $layout Raw semantic layout.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitize_layout( array $layout ): array {
		$sanitized = array();

		foreach ( $layout as $node ) {
			if ( ! is_array( $node ) ) {
				$sanitized[] = $node;
				continue;
			}

			$clean = $node;

			if ( isset( $clean['label'] ) && is_string( $clean['label'] ) ) {
				$clean['label'] = sanitize_text_field( $clean['label'] );
			}

			if ( isset( $clean['content'] ) && is_string( $clean['content'] ) ) {
				$type = isset( $clean['type'] ) && is_string( $clean['type'] ) ? $clean['type'] : '';

				if ( 'code' === $type && current_user_can( 'unfiltered_html' ) ) {
					$clean['content'] = $clean['content'];
				} elseif ( in_array( $type, array( 'text', 'code' ), true ) ) {
					$clean['content'] = wp_kses_post( $clean['content'] );
				} else {
					$clean['content'] = sanitize_text_field( $clean['content'] );
				}
			}

			if ( isset( $clean['attributes'] ) && is_array( $clean['attributes'] ) ) {
				foreach ( $clean['attributes'] as $key => $value ) {
					if ( ! is_scalar( $value ) ) {
						continue;
					}

					$string_value = (string) $value;

					if ( is_string( $key ) && ( false !== strpos( $key, 'url' ) || false !== strpos( $key, 'src' ) ) ) {
						$clean['attributes'][ $key ] = esc_url_raw( $string_value );
					} else {
						$clean['attributes'][ $key ] = sanitize_text_field( $string_value );
					}
				}
			}

			if ( isset( $clean['children'] ) && is_array( $clean['children'] ) ) {
				$clean['children'] = self::sanitize_layout( $clean['children'] );
			}

			$sanitized[] = $clean;
		}

		return $sanitized;
	}

	private static function ensure_placeholder_wrapper( string $content ): string {
		if ( false !== strpos( $content, '<!-- wp:divi/placeholder' ) ) {
			return $content;
		}

		return '<!-- wp:divi/placeholder -->' . $content . '<!-- /wp:divi/placeholder -->';
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 */
	private static function count_divi_blocks( array $blocks ): int {
		$count = 0;

		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? null;

			if ( is_string( $name ) && 0 === strpos( $name, 'divi/' ) && 'divi/placeholder' !== $name ) {
				++$count;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$count += self::count_divi_blocks( $block['innerBlocks'] );
			}
		}

		return $count;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private static function describe_blocks( array $blocks, string $prefix = '' ): array {
		$described = array();

		foreach ( $blocks as $index => $block ) {
			$name = $block['blockName'] ?? null;

			if ( ! is_string( $name ) ) {
				continue;
			}

			$path  = '' === $prefix ? (string) $index : $prefix . '.' . $index;
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

			$described[] = array(
				'path'       => $path,
				'name'       => $name,
				'attributes' => $attrs,
				'children'   => ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] )
					? self::describe_blocks( $block['innerBlocks'], $path )
					: array(),
			);
		}

		return $described;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param array<int, int>                  $indexes Path indexes.
	 * @return array<string, mixed>|null
	 */
	private static function &block_by_path( array &$blocks, array $indexes ): ?array {
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

		return self::block_by_path( $blocks[ $index ]['innerBlocks'], $indexes );
	}

	/**
	 * @param array<string, mixed> $current Current attributes.
	 * @param array<string, mixed> $patch Patch attributes.
	 * @return array<string, mixed>
	 */
	private static function merge_attributes( array $current, array $patch ): array {
		foreach ( $patch as $key => $value ) {
			if ( is_array( $value ) && isset( $current[ $key ] ) && is_array( $current[ $key ] ) ) {
				$current[ $key ] = self::merge_attributes( $current[ $key ], $value );
			} else {
				$current[ $key ] = $value;
			}
		}

		return $current;
	}

	private static function save_revision( int $post_id ): ?int {
		if ( ! function_exists( 'wp_save_post_revision' ) ) {
			return null;
		}

		$revision_id = wp_save_post_revision( $post_id );

		return is_int( $revision_id ) && $revision_id > 0 ? $revision_id : null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function failure( int $post_id, string $code, string $message ): array {
		return array(
			'success'       => false,
			'post_id'       => $post_id,
			'error_code'    => $code,
			'error_message' => $message,
		);
	}

	private function __construct() {
	}
}
