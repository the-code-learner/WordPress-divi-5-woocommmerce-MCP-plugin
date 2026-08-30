<?php
/**
 * Normalized clean-break Divi document AST.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

final class DocumentModel {
	/**
	 * Read a WordPress post into a normalized Divi document snapshot.
	 *
	 * @return array<string, mixed>
	 */
	public static function get( int $post_id, bool $include_native = false ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return self::failure( $post_id, 'post_not_found', 'The requested post does not exist.' );
		}

		$content        = (string) $post->post_content;
		$blocks         = parse_blocks( $content );
		$document_token = hash( 'sha256', $post_id . '|' . $content );
		$catalog        = RuntimeModuleRegistry::catalog();
		$descriptors    = self::descriptor_map( isset( $catalog['modules'] ) && is_array( $catalog['modules'] ) ? $catalog['modules'] : array() );
		$ast            = self::build_ast( $blocks, $document_token, $include_native, $descriptors );
		$result         = array();

		$result['success']         = true;
		$result['post_id']         = $post_id;
		$result['post_status']     = (string) $post->post_status;
		$result['builder_enabled'] = 'on' === get_post_meta( $post_id, '_et_pb_use_builder', true );
		$result['document_token']  = $document_token;
		$result['handle_scope']    = 'document_snapshot';
		$result['identity_note']   = 'Handles are stable within this exact document snapshot and future atomic mutation batch. Numeric paths are locators only and may shift after structural edits.';
		$result['nodes']           = $ast;
		$result['node_count']      = self::count_nodes( $ast );
		$result['include_native']  = $include_native;
		$result['error_code']      = null;
		$result['error_message']   = null;

		return $result;
	}

	/**
	 * Build a normalized AST from parsed WordPress blocks.
	 *
	 * This method is intentionally pure so snapshot identity can be regression tested
	 * without bootstrapping WordPress.
	 *
	 * @param array<int, array<string, mixed>>    $blocks Parsed blocks.
	 * @param array<string, array<string, mixed>> $descriptors Runtime descriptors keyed by module name.
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_ast( array $blocks, string $document_token, bool $include_native = false, array $descriptors = array() ): array {
		$ordinal = 0;

		return self::describe_blocks( $blocks, $document_token, $include_native, $descriptors, $ordinal );
	}

	/**
	 * Derive the deterministic handle used by one exact document snapshot.
	 */
	public static function snapshot_handle( string $document_token, int $ordinal, string $name ): string {
		return 'node-' . substr( hash( 'sha256', $document_token . '|' . $ordinal . '|' . $name ), 0, 20 );
	}

	/**
	 * @param array<int, array<string, mixed>>    $blocks Parsed blocks.
	 * @param array<string, array<string, mixed>> $descriptors Runtime descriptors.
	 * @return array<int, array<string, mixed>>
	 */
	private static function describe_blocks(
		array $blocks,
		string $document_token,
		bool $include_native,
		array $descriptors,
		int &$ordinal,
		string $prefix = '',
		?string $parent_handle = null
	): array {
		$nodes = array();

		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = $block['blockName'] ?? null;

			if ( ! is_string( $name ) || '' === $name ) {
				continue;
			}

			$path       = '' === $prefix ? (string) $index : $prefix . '.' . $index;
			$handle     = self::snapshot_handle( $document_token, $ordinal, $name );
			$descriptor = isset( $descriptors[ $name ] ) && is_array( $descriptors[ $name ] ) ? $descriptors[ $name ] : null;
			$attrs      = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$kind       = 'foreign-block';

			if ( 'divi/placeholder' === $name ) {
				$kind = 'document-root';
			} elseif ( null !== $descriptor ) {
				$kind = 'module';
			}

			++$ordinal;
			$children = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] )
				? self::describe_blocks( $block['innerBlocks'], $document_token, $include_native, $descriptors, $ordinal, $path, $handle )
				: array();

			$node = array(
				'handle'                => $handle,
				'handle_scope'          => 'document_snapshot',
				'numeric_path'          => $path,
				'parent_handle'         => $parent_handle,
				'kind'                  => $kind,
				'module_type'           => $name,
				'normalized_properties' => null !== $descriptor ? self::normalized_properties( $attrs, $descriptor ) : array(),
				'children'              => $children,
				'provider'              => null !== $descriptor && isset( $descriptor['provider'] ) ? $descriptor['provider'] : array(
					'id'         => self::namespace_from_name( $name ),
					'provenance' => 'block_namespace',
				),
				'provenance'            => null !== $descriptor && isset( $descriptor['provenance'] ) ? $descriptor['provenance'] : array(
					'source' => 'wordpress_block_document',
				),
				'compatibility_mode'    => null !== $descriptor && isset( $descriptor['compatibility_mode'] ) ? $descriptor['compatibility_mode'] : 'unknown',
				'capabilities'          => null !== $descriptor && isset( $descriptor['capabilities'] ) ? $descriptor['capabilities'] : array(),
				'nesting'               => array(
					'parent_constraints'   => null !== $descriptor && isset( $descriptor['parent'] ) ? $descriptor['parent'] : array(),
					'ancestor_constraints' => null !== $descriptor && isset( $descriptor['ancestor'] ) ? $descriptor['ancestor'] : array(),
					'allowed_children'     => null !== $descriptor && isset( $descriptor['allowed_children'] ) ? $descriptor['allowed_children'] : array(),
				),
				'authoring'             => array(
					'schema_available'  => null !== $descriptor,
					'clean_break_write' => null !== $descriptor ? 'supported' : 'unavailable',
				),
			);

			if ( $include_native ) {
				$node['native'] = array(
					'block_name'     => $name,
					'raw_attributes' => $attrs,
				);
			}

			$nodes[] = $node;
		}

		return $nodes;
	}

	/**
	 * @param array<string, mixed> $attributes Native attributes.
	 * @param array<string, mixed> $descriptor Runtime module descriptor.
	 * @return array<string, mixed>
	 */
	private static function normalized_properties( array $attributes, array $descriptor ): array {
		$parameters = isset( $descriptor['parameter_graph'] ) && is_array( $descriptor['parameter_graph'] )
			? $descriptor['parameter_graph']
			: ( isset( $descriptor['parameters'] ) && is_array( $descriptor['parameters'] ) ? $descriptor['parameters'] : array() );

		return RuntimeAttributeNormalizer::normalize( $attributes, $parameters );
	}

	/**
	 * @param array<int, array<string, mixed>> $modules Runtime module records.
	 * @return array<string, array<string, mixed>>
	 */
	private static function descriptor_map( array $modules ): array {
		$map = array();

		foreach ( $modules as $module ) {
			if ( isset( $module['name'] ) && is_string( $module['name'] ) ) {
				$map[ $module['name'] ] = $module;
			}
		}

		return $map;
	}

	private static function namespace_from_name( string $name ): string {
		$parts = explode( '/', $name, 2 );

		return isset( $parts[0] ) && '' !== $parts[0] ? $parts[0] : 'unknown';
	}

	/**
	 * @param array<int, array<string, mixed>> $nodes AST nodes.
	 */
	private static function count_nodes( array $nodes ): int {
		$count = 0;

		foreach ( $nodes as $node ) {
			++$count;

			if ( isset( $node['children'] ) && is_array( $node['children'] ) ) {
				$count += self::count_nodes( $node['children'] );
			}
		}

		return $count;
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
