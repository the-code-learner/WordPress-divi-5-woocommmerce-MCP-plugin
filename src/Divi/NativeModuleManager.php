<?php
/**
 * Runtime-native Divi 5 module insertion.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

use InvalidArgumentException;
use Throwable;

final class NativeModuleManager {
	/**
	 * Insert one registered native Divi module tree into an existing draft.
	 *
	 * @param array<string, mixed> $node Native module node.
	 * @return array<string, mixed>
	 */
	public static function insert( int $post_id, string $parent_path, int $index, array $node ): array {
		$state = self::editable_tree( $post_id );

		if ( isset( $state['error'] ) && is_array( $state['error'] ) ) {
			return $state['error'];
		}

		$blocks = $state['blocks'];
		$parent = BlockTreeEditor::get( $blocks, $parent_path );

		if ( null === $parent ) {
			return self::failure( $post_id, 'parent_not_found', 'No block exists at the requested destination parent path.' );
		}

		$parent_name = $parent['blockName'] ?? null;

		if ( ! is_string( $parent_name ) ) {
			return self::failure( $post_id, 'invalid_parent', 'The destination path does not identify a Divi container.' );
		}

		try {
			self::validate_node( $node, $parent_name );
			$serialized_node = NativeModuleSerializer::to_block( $node, $parent_name );
			$parsed_node     = parse_blocks( $serialized_node );

			if ( 1 !== count( $parsed_node ) || ! is_array( $parsed_node[0] ) ) {
				return self::failure( $post_id, 'module_serialization_failed', 'The native module tree did not produce exactly one Divi block.' );
			}

			$blocks = BlockTreeEditor::insert( $blocks, $parent_path, $index, $parsed_node[0] );
		} catch ( InvalidArgumentException $exception ) {
			return self::failure( $post_id, 'invalid_native_module', $exception->getMessage() );
		} catch ( Throwable $throwable ) {
			return self::failure( $post_id, 'invalid_structure_operation', $throwable->getMessage() );
		}

		$serialized = serialize_blocks( $blocks );

		if ( ! self::is_usable_native_content( $serialized ) ) {
			return self::failure( $post_id, 'invalid_native_layout', 'The operation would leave the post without a usable native Divi 5 layout.' );
		}

		$revision_id = self::save_revision( $post_id );
		$updated_id  = wp_update_post(
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

		$result                  = LayoutManager::inspect( $post_id );
		$result['revision_id']   = $revision_id;
		$result['write_method']  = 'native-runtime-module-insert';
		$result['operation']     = 'insert-native';
		$result['parent_path']   = $parent_path;
		$result['updated_path']  = $parent_path . '.' . $index;
		$result['updated_block'] = isset( $node['module_name'] ) && is_string( $node['module_name'] ) ? $node['module_name'] : '';

		return $result;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function editable_tree( int $post_id ): array {
		if ( ! LayoutManager::is_native_authoring_available() ) {
			return array( 'error' => self::failure( $post_id, 'divi5_native_authoring_unavailable', 'Divi 5 native block APIs are not available on this site.' ) );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return array( 'error' => self::failure( $post_id, 'post_not_found', 'The requested post does not exist.' ) );
		}

		if ( ! in_array( (string) $post->post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
			return array( 'error' => self::failure( $post_id, 'draft_required', 'Divi native module writes are restricted to draft or pending content.' ) );
		}

		$blocks = parse_blocks( (string) $post->post_content );

		if ( 0 === self::count_divi_blocks( $blocks ) ) {
			return array( 'error' => self::failure( $post_id, 'native_layout_required', 'The post does not contain an editable native Divi 5 layout.' ) );
		}

		return array(
			'post'   => $post,
			'blocks' => $blocks,
		);
	}

	/**
	 * @param array<string, mixed> $node Native module node.
	 */
	private static function validate_node( array $node, string $parent_name ): void {
		$allowed_keys = array( 'module_name', 'attributes', 'children' );
		$unknown_keys = array_diff( array_keys( $node ), $allowed_keys );

		if ( array() !== $unknown_keys ) {
			throw new InvalidArgumentException( 'Native module nodes contain unsupported properties.' );
		}

		$module_name = $node['module_name'] ?? null;

		if ( ! is_string( $module_name )
			|| 1 !== preg_match( '/^divi\/[a-z0-9-]+$/', $module_name )
			|| ! LayoutManager::is_semantic_native_block_name( $module_name )
		) {
			throw new InvalidArgumentException( 'Native module_name must identify a semantic divi/* block.' );
		}

		$schema = ModuleRegistry::schema( $module_name );

		if ( empty( $schema['success'] ) ) {
			throw new InvalidArgumentException( 'The requested native Divi module is not registered on this site.' );
		}

		if ( ! ModuleRegistry::allows_child( $parent_name, $module_name ) ) {
			throw new InvalidArgumentException( 'The destination module does not accept this Divi child module type.' );
		}

		if ( isset( $node['attributes'] ) && ! is_array( $node['attributes'] ) ) {
			throw new InvalidArgumentException( 'Native module attributes must be an object.' );
		}

		$children = $node['children'] ?? array();

		if ( ! is_array( $children ) ) {
			throw new InvalidArgumentException( 'Native module children must be an array.' );
		}

		foreach ( $children as $child ) {
			if ( ! is_array( $child ) ) {
				throw new InvalidArgumentException( 'Each native child module must be an object.' );
			}

			self::validate_node( $child, $module_name );
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 */
	private static function count_divi_blocks( array $blocks ): int {
		$count = 0;

		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? null;

			if ( LayoutManager::is_semantic_native_block_name( $name ) ) {
				++$count;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$count += self::count_divi_blocks( $block['innerBlocks'] );
			}
		}

		return $count;
	}

	private static function is_usable_native_content( string $content ): bool {
		if ( false !== strpos( $content, '<!-- wp:divi/shortcode-module' ) ) {
			return false;
		}

		return self::count_divi_blocks( parse_blocks( $content ) ) > 0;
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
