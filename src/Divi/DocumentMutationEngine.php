<?php
/**
 * Atomic clean-break Divi document validation and mutation planning.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

use Throwable;

final class DocumentMutationEngine {
	private const INTERNAL_HANDLE = '__mcp_document_handle';

	/**
	 * Dry-run a mutation batch against one exact document snapshot.
	 *
	 * @param array<int, array<string, mixed>> $operations Mutation batch.
	 * @return array<string, mixed>
	 */
	public static function validate( int $post_id, string $document_token, array $operations ): array {
		$state = self::load_state( $post_id, $document_token );

		if ( isset( $state['error'] ) ) {
			return $state['error'];
		}

		$plan = self::plan(
			$state['blocks'],
			$document_token,
			$operations,
			self::runtime_descriptor_map()
		);

		$status = (string) $state['post']->post_status;

		$plan['post_id']        = $post_id;
		$plan['post_status']    = $status;
		$plan['write_eligible'] = self::is_write_status( $status );

		if ( ! $plan['write_eligible'] ) {
			$plan['valid'] = false;

			$plan['errors'][] = self::error(
				'draft_required',
				null,
				null,
				null,
				$status,
				array( 'draft', 'pending', 'auto-draft' ),
				'Document mutation persistence is restricted to draft, pending, or auto-draft posts.'
			);
		}

		return $plan;
	}

	/**
	 * Validate the complete batch, then persist exactly once.
	 *
	 * @param array<int, array<string, mixed>> $operations Mutation batch.
	 * @return array<string, mixed>
	 */
	public static function mutate( int $post_id, string $document_token, array $operations ): array {
		if ( ! LayoutManager::is_native_authoring_available() ) {
			return self::failure(
				$post_id,
				'divi5_native_authoring_unavailable',
				'Divi 5 native block APIs are not available on this site.'
			);
		}

		$state = self::load_state( $post_id, $document_token );

		if ( isset( $state['error'] ) ) {
			return $state['error'];
		}

		if ( ! self::is_write_status( (string) $state['post']->post_status ) ) {
			return self::failure(
				$post_id,
				'draft_required',
				'Divi document mutations are restricted to draft, pending, or auto-draft posts.'
			);
		}

		$plan = self::plan(
			$state['blocks'],
			$document_token,
			$operations,
			self::runtime_descriptor_map()
		);

		if ( empty( $plan['valid'] ) ) {
			return array(
				'success'       => false,
				'post_id'       => $post_id,
				'valid'         => false,
				'persisted'     => false,
				'errors'        => $plan['errors'],
				'error_code'    => 'batch_validation_failed',
				'error_message' => 'The mutation batch is invalid. No operation was persisted.',
			);
		}

		$serialized = serialize_blocks( self::strip_internal_handles( $plan['blocks'] ) );

		$revision_id = self::save_revision( $post_id );

		$updated_id = wp_update_post(
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

		$document = DocumentModel::get( $post_id, false );

		$document['valid']        = true;
		$document['persisted']    = true;
		$document['revision_id']  = $revision_id;
		$document['operations']   = $plan['operations'];
		$document['created']      = $plan['created'];
		$document['write_method'] = 'clean-break-atomic-document-mutation';

		return $document;
	}

	/**
	 * Pure mutation planner used by dry-runs and isolated unit tests.
	 *
	 * @param array<int, array<string, mixed>>    $blocks Parsed WordPress blocks.
	 * @param array<int, array<string, mixed>>    $operations Mutation batch.
	 * @param array<string, array<string, mixed>> $descriptors Runtime module descriptors.
	 * @return array<string, mixed>
	 */
	public static function plan( array $blocks, string $document_token, array $operations, array $descriptors ): array {
		$working = self::annotate_snapshot_handles( $blocks, $document_token );

		$errors = array();

		$results = array();

		$created = array();

		if ( array() === $operations ) {
			$errors[] = self::error(
				'empty_batch',
				null,
				null,
				null,
				null,
				'one or more mutation operations',
				'Mutation batches must contain at least one operation.'
			);
		}

		foreach ( $operations as $index => $operation ) {
			if ( ! is_array( $operation ) ) {
				$errors[] = self::error(
					'invalid_operation',
					(int) $index,
					null,
					null,
					$operation,
					'object',
					'Each mutation operation must be an object.'
				);

				continue;
			}

			$result = self::apply_operation(
				$working,
				$operation,
				(int) $index,
				$document_token,
				$descriptors
			);

			if ( isset( $result['error'] ) ) {
				$errors[] = $result['error'];
				continue;
			}

			$working = $result['blocks'];

			$results[] = $result['result'];

			if ( isset( $result['created'] ) && is_array( $result['created'] ) ) {
				$created = array_merge( $created, $result['created'] );
			}
		}

		return array(
			'success'        => true,
			'valid'          => array() === $errors,
			'persisted'      => false,
			'document_token' => $document_token,
			'operations'     => $results,
			'created'        => $created,
			'errors'         => $errors,
			'blocks'         => $working,
			'error_code'     => null,
			'error_message'  => null,
		);
	}

	/**
	 * @param array<int, array<string, mixed>>    $blocks Working tree.
	 * @param array<string, mixed>                $operation Operation.
	 * @param array<string, array<string, mixed>> $descriptors Runtime descriptors.
	 * @return array<string, mixed>
	 */
	private static function apply_operation( array $blocks, array $operation, int $index, string $document_token, array $descriptors ): array {
		$op = self::string_field( $operation, 'op' );

		switch ( $op ) {
			case 'insert':
				return self::insert( $blocks, $operation, $index, $document_token, $descriptors );

			case 'set':
				return self::set_property( $blocks, $operation, $index, $descriptors );

			case 'delete':
				return self::delete( $blocks, $operation, $index, $descriptors );

			case 'move':
				return self::move( $blocks, $operation, $index, $descriptors );

			case 'duplicate':
				return self::duplicate( $blocks, $operation, $index, $document_token, $descriptors );

			case 'responsive':
				return self::set_responsive( $blocks, $operation, $index, $descriptors );

			case 'state':
				return self::unsupported_semantic_operation(
					$index,
					$operation,
					'state_mapping_unavailable',
					'The runtime parameter graph does not yet expose a safe native state-write mapping.'
				);

			case 'preset':
				return self::unsupported_semantic_operation(
					$index,
					$operation,
					'preset_mapping_unavailable',
					'The runtime parameter graph does not yet expose a safe native preset-application mapping.'
				);

			default:
				return self::error_result(
					'unsupported_operation',
					$index,
					null,
					'op',
					$op,
					array( 'insert', 'set', 'delete', 'move', 'duplicate', 'responsive', 'state', 'preset' ),
					'Unsupported document mutation operation.'
				);
		}
	}

	/**
	 * @param array<int, array<string, mixed>>    $blocks Working tree.
	 * @param array<string, mixed>                $operation Operation.
	 * @param array<string, array<string, mixed>> $descriptors Runtime descriptors.
	 * @return array<string, mixed>
	 */
	private static function insert( array $blocks, array $operation, int $index, string $document_token, array $descriptors ): array {
		$parent_handle = self::string_field( $operation, 'parent' );

		$parent = self::resolve_block( $blocks, $parent_handle );

		if ( null === $parent ) {
			return self::error_result(
				'parent_not_found',
				$index,
				$parent_handle,
				'parent',
				$parent_handle,
				'existing document handle',
				'Insert parent handle does not exist in the current batch state.'
			);
		}

		$node = isset( $operation['node'] ) && is_array( $operation['node'] ) ? $operation['node'] : null;

		if ( null === $node ) {
			return self::error_result(
				'invalid_node',
				$index,
				$parent_handle,
				'node',
				$operation['node'] ?? null,
				'object',
				'Insert operations require a node object.'
			);
		}

		$built = self::build_node( $node, $index, $document_token, $descriptors );

		if ( isset( $built['error'] ) ) {
			return $built;
		}

		$parent_name = self::block_name( $parent['block'] );

		$child_name = self::block_name( $built['block'] );

		if ( ! self::allows_child( $parent_name, $child_name, $descriptors ) ) {
			return self::hierarchy_error(
				$index,
				$built['handle'],
				$parent_name,
				$descriptors
			);
		}

		$child_index = self::child_index( $operation );

		try {
			$blocks = BlockTreeEditor::insert(
				$blocks,
				$parent['path'],
				$child_index,
				$built['block']
			);
		} catch ( Throwable $throwable ) {
			return self::structure_error( $index, $built['handle'], $child_index, $throwable );
		}

		return array(
			'blocks'  => $blocks,
			'created' => $built['created'],
			'result'  => array(
				'op'     => 'insert',
				'handle' => $built['handle'],
				'parent' => $parent_handle,
				'index'  => $child_index,
				'module' => $child_name,
			),
		);
	}

	/**
	 * @param array<int, array<string, mixed>>    $blocks Working tree.
	 * @param array<string, mixed>                $operation Operation.
	 * @param array<string, array<string, mixed>> $descriptors Runtime descriptors.
	 * @return array<string, mixed>
	 */
	private static function set_property( array $blocks, array $operation, int $index, array $descriptors ): array {
		$handle = self::string_field( $operation, 'handle' );

		$property = self::string_field( $operation, 'property' );

		if ( ! array_key_exists( 'value', $operation ) ) {
			return self::error_result(
				'missing_value',
				$index,
				$handle,
				$property,
				null,
				'property value',
				'Set operations require a value.'
			);
		}

		$resolved = self::resolve_module( $blocks, $handle, $index, $descriptors );

		if ( isset( $resolved['error'] ) ) {
			return $resolved;
		}

		$parameter = self::parameter( $resolved['descriptor'], $property );

		if ( null === $parameter ) {
			return self::unknown_property_error( $index, $handle, $property, $operation['value'] );
		}

		$value_error = self::validate_value( $operation['value'], $parameter );

		if ( null !== $value_error ) {
			return self::invalid_value_error(
				$index,
				$handle,
				$property,
				$operation['value'],
				$value_error
			);
		}

		$block = $resolved['block'];

		$attrs = self::block_attrs( $block );

		$attrs = self::set_path_value(
			$attrs,
			self::base_value_path( $parameter ),
			$operation['value']
		);

		$block['attrs'] = $attrs;

		$blocks = self::replace_block( $blocks, $resolved['path'], $block );

		return array(
			'blocks' => $blocks,
			'result' => array(
				'op'       => 'set',
				'handle'   => $handle,
				'property' => $property,
			),
		);
	}

	/**
	 * @param array<int, array<string, mixed>>    $blocks Working tree.
	 * @param array<string, mixed>                $operation Operation.
	 * @param array<string, array<string, mixed>> $descriptors Runtime descriptors.
	 * @return array<string, mixed>
	 */
	private static function delete( array $blocks, array $operation, int $index, array $descriptors ): array {
		$handle = self::string_field( $operation, 'handle' );

		$resolved = self::resolve_module( $blocks, $handle, $index, $descriptors );

		if ( isset( $resolved['error'] ) ) {
			return $resolved;
		}

		try {
			$blocks = BlockTreeEditor::delete( $blocks, $resolved['path'] );
		} catch ( Throwable $throwable ) {
			return self::error_result(
				'invalid_structure_operation',
				$index,
				$handle,
				null,
				null,
				'deletable non-root module',
				$throwable->getMessage()
			);
		}

		return array(
			'blocks' => $blocks,
			'result' => array(
				'op'     => 'delete',
				'handle' => $handle,
			),
		);
	}

	/**
	 * @param array<int, array<string, mixed>>    $blocks Working tree.
	 * @param array<string, mixed>                $operation Operation.
	 * @param array<string, array<string, mixed>> $descriptors Runtime descriptors.
	 * @return array<string, mixed>
	 */
	private static function move( array $blocks, array $operation, int $index, array $descriptors ): array {
		$handle = self::string_field( $operation, 'handle' );

		$resolved = self::resolve_module( $blocks, $handle, $index, $descriptors );

		if ( isset( $resolved['error'] ) ) {
			return $resolved;
		}

		$parent_handle = self::string_field( $operation, 'parent' );

		$parent = self::resolve_block( $blocks, $parent_handle );

		if ( null === $parent ) {
			return self::error_result(
				'parent_not_found',
				$index,
				$handle,
				'parent',
				$parent_handle,
				'existing document handle',
				'Move destination parent does not exist.'
			);
		}

		$parent_name = self::block_name( $parent['block'] );

		$module_name = self::block_name( $resolved['block'] );

		if ( ! self::allows_child( $parent_name, $module_name, $descriptors ) ) {
			return self::hierarchy_error( $index, $handle, $parent_name, $descriptors );
		}

		$child_index = self::child_index( $operation );

		try {
			$blocks = BlockTreeEditor::move(
				$blocks,
				$resolved['path'],
				$parent['path'],
				$child_index
			);
		} catch ( Throwable $throwable ) {
			return self::structure_error( $index, $handle, $child_index, $throwable );
		}

		return array(
			'blocks' => $blocks,
			'result' => array(
				'op'     => 'move',
				'handle' => $handle,
				'parent' => $parent_handle,
				'index'  => $child_index,
			),
		);
	}

	/**
	 * @param array<int, array<string, mixed>>    $blocks Working tree.
	 * @param array<string, mixed>                $operation Operation.
	 * @param array<string, array<string, mixed>> $descriptors Runtime descriptors.
	 * @return array<string, mixed>
	 */
	private static function duplicate( array $blocks, array $operation, int $index, string $document_token, array $descriptors ): array {
		$handle = self::string_field( $operation, 'handle' );

		$resolved = self::resolve_module( $blocks, $handle, $index, $descriptors );

		if ( isset( $resolved['error'] ) ) {
			return $resolved;
		}

		$parent_handle = self::string_field( $operation, 'parent' );

		$parent = self::resolve_block( $blocks, $parent_handle );

		if ( null === $parent ) {
			return self::error_result(
				'parent_not_found',
				$index,
				$handle,
				'parent',
				$parent_handle,
				'existing document handle',
				'Duplicate destination parent does not exist.'
			);
		}

		$parent_name = self::block_name( $parent['block'] );

		$module_name = self::block_name( $resolved['block'] );

		if ( ! self::allows_child( $parent_name, $module_name, $descriptors ) ) {
			return self::hierarchy_error( $index, $handle, $parent_name, $descriptors );
		}

		$new_handle = self::new_handle(
			$operation,
			$document_token,
			$index,
			$module_name
		);

		$seed = 0;

		$created = array();

		$copy = self::refresh_subtree_handles(
			$resolved['block'],
			$new_handle,
			$document_token,
			$index,
			$seed,
			$created
		);

		$child_index = self::child_index( $operation );

		try {
			$blocks = BlockTreeEditor::insert(
				$blocks,
				$parent['path'],
				$child_index,
				$copy
			);
		} catch ( Throwable $throwable ) {
			return self::structure_error( $index, $new_handle, $child_index, $throwable );
		}

		return array(
			'blocks'  => $blocks,
			'created' => $created,
			'result'  => array(
				'op'      => 'duplicate',
				'handle'  => $handle,
				'created' => $new_handle,
				'parent'  => $parent_handle,
				'index'   => $child_index,
			),
		);
	}

	/**
	 * @param array<int, array<string, mixed>>    $blocks Working tree.
	 * @param array<string, mixed>                $operation Operation.
	 * @param array<string, array<string, mixed>> $descriptors Runtime descriptors.
	 * @return array<string, mixed>
	 */
	private static function set_responsive( array $blocks, array $operation, int $index, array $descriptors ): array {
		$handle = self::string_field( $operation, 'handle' );

		$property = self::string_field( $operation, 'property' );

		$device = self::string_field( $operation, 'device' );

		if ( ! array_key_exists( 'value', $operation ) ) {
			return self::error_result(
				'missing_value',
				$index,
				$handle,
				$property,
				null,
				'responsive value',
				'Responsive operations require a value.'
			);
		}

		$resolved = self::resolve_module( $blocks, $handle, $index, $descriptors );

		if ( isset( $resolved['error'] ) ) {
			return $resolved;
		}

		$parameter = self::parameter( $resolved['descriptor'], $property );

		if ( null === $parameter ) {
			return self::unknown_property_error( $index, $handle, $property, $operation['value'] );
		}

		$devices = isset( $parameter['devices'] ) && is_array( $parameter['devices'] )
			? $parameter['devices']
			: array();

		if ( ! in_array( $device, $devices, true ) ) {
			return self::error_result(
				'responsive_device_unavailable',
				$index,
				$handle,
				$property,
				$device,
				$devices,
				'The runtime parameter metadata does not expose the requested responsive device.'
			);
		}

		$native_path = self::responsive_value_path( $parameter, $device );

		if ( null === $native_path ) {
			return self::error_result(
				'responsive_mapping_unavailable',
				$index,
				$handle,
				$property,
				$operation['value'],
				'runtime-proven responsive native value path',
				'The runtime demonstrates responsive support but not a safe writable value mapping for this parameter.'
			);
		}

		$value_error = self::validate_value( $operation['value'], $parameter );

		if ( null !== $value_error ) {
			return self::invalid_value_error(
				$index,
				$handle,
				$property,
				$operation['value'],
				$value_error
			);
		}

		$block = $resolved['block'];

		$attrs = self::set_path_value(
			self::block_attrs( $block ),
			$native_path,
			$operation['value']
		);

		$block['attrs'] = $attrs;

		$blocks = self::replace_block( $blocks, $resolved['path'], $block );

		return array(
			'blocks' => $blocks,
			'result' => array(
				'op'       => 'responsive',
				'handle'   => $handle,
				'property' => $property,
				'device'   => $device,
			),
		);
	}

	/**
	 * Build and validate one semantic subtree.
	 *
	 * @param array<string, mixed>                $node Semantic node.
	 * @param array<string, array<string, mixed>> $descriptors Runtime descriptors.
	 * @return array<string, mixed>
	 */
	private static function build_node( array $node, int $operation_index, string $document_token, array $descriptors, ?string $parent_name = null, int $salt = 0 ): array {
		$module_name = self::string_field( $node, 'module_type' );

		if ( '' === $module_name || ! isset( $descriptors[ $module_name ] ) ) {
			return self::error_result(
				'module_not_registered',
				$operation_index,
				null,
				'module_type',
				$module_name,
				'compatible module registered in the active Divi runtime',
				'The requested module type is not exposed by the active runtime.'
			);
		}

		if ( null !== $parent_name && ! self::allows_child( $parent_name, $module_name, $descriptors ) ) {
			return self::hierarchy_error( $operation_index, null, $parent_name, $descriptors );
		}

		$descriptor = $descriptors[ $module_name ];

		$handle = self::new_handle(
			$node,
			$document_token,
			$operation_index,
			$module_name,
			$salt
		);

		$attrs = array();

		$properties = isset( $node['properties'] ) && is_array( $node['properties'] )
			? $node['properties']
			: array();

		foreach ( $properties as $property => $value ) {
			if ( ! is_string( $property ) ) {
				return self::error_result(
					'invalid_property_name',
					$operation_index,
					$handle,
					null,
					$property,
					'semantic property path string',
					'Inserted node property names must be semantic path strings.'
				);
			}

			$parameter = self::parameter( $descriptor, $property );

			if ( null === $parameter ) {
				return self::unknown_property_error( $operation_index, $handle, $property, $value );
			}

			$value_error = self::validate_value( $value, $parameter );

			if ( null !== $value_error ) {
				return self::invalid_value_error(
					$operation_index,
					$handle,
					$property,
					$value,
					$value_error
				);
			}

			$attrs = self::set_path_value(
				$attrs,
				self::base_value_path( $parameter ),
				$value
			);
		}

		$children = isset( $node['children'] ) && is_array( $node['children'] )
			? $node['children']
			: array();

		$built_children = array();

		$created = array( $handle );

		foreach ( $children as $child_index => $child ) {
			if ( ! is_array( $child ) ) {
				return self::error_result(
					'invalid_node',
					$operation_index,
					$handle,
					'children.' . $child_index,
					$child,
					'node object',
					'Each inserted child must be a node object.'
				);
			}

			$built = self::build_node(
				$child,
				$operation_index,
				$document_token,
				$descriptors,
				$module_name,
				$salt + (int) $child_index + 1
			);

			if ( isset( $built['error'] ) ) {
				return $built;
			}

			$built_children[] = $built['block'];

			$created = array_merge( $created, $built['created'] );
		}

		$block = array(
			'blockName'    => $module_name,
			'attrs'        => $attrs,
			'innerBlocks'  => $built_children,
			'innerHTML'    => str_repeat( "\n", count( $built_children ) + 1 ),
			'innerContent' => self::child_slots( count( $built_children ) ),
		);

		$block[ self::INTERNAL_HANDLE ] = $handle;

		return array(
			'block'   => $block,
			'handle'  => $handle,
			'created' => $created,
		);
	}

	/**
	 * Resolve a handle and require an authorable runtime module.
	 *
	 * @param array<int, array<string, mixed>>    $blocks Working tree.
	 * @param array<string, array<string, mixed>> $descriptors Runtime descriptors.
	 * @return array<string, mixed>
	 */
	private static function resolve_module( array $blocks, string $handle, int $index, array $descriptors ): array {
		$resolved = self::resolve_block( $blocks, $handle );

		if ( null === $resolved ) {
			return self::error_result(
				'node_not_found',
				$index,
				$handle,
				'handle',
				$handle,
				'existing document or batch-created handle',
				'The requested node handle does not exist in the current batch state.'
			);
		}

		$module_name = self::block_name( $resolved['block'] );

		if ( ! isset( $descriptors[ $module_name ] ) ) {
			return self::error_result(
				'node_not_authorable',
				$index,
				$handle,
				'module_type',
				$module_name,
				'compatible module registered in the active Divi runtime',
				'The node is not exposed as an authorable Divi runtime module.'
			);
		}

		$resolved['descriptor'] = $descriptors[ $module_name ];

		return $resolved;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Working tree.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_block( array $blocks, string $handle ): ?array {
		$path = self::path_for_handle( $blocks, $handle );

		if ( null === $path ) {
			return null;
		}

		$block = BlockTreeEditor::get( $blocks, $path );

		if ( null === $block ) {
			return null;
		}

		return array(
			'path'  => $path,
			'block' => $block,
		);
	}

	/**
	 * Annotate the starting snapshot with in-memory handles.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private static function annotate_snapshot_handles( array $blocks, string $document_token ): array {
		$ordinal = 0;

		return self::annotate_nodes( $blocks, $document_token, $ordinal );
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private static function annotate_nodes( array $blocks, string $document_token, int &$ordinal ): array {
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = $block['blockName'] ?? null;

			if ( ! is_string( $name ) || '' === $name ) {
				continue;
			}

			$blocks[ $index ][ self::INTERNAL_HANDLE ] = DocumentModel::snapshot_handle(
				$document_token,
				$ordinal,
				$name
			);

			++$ordinal;

			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$blocks[ $index ]['innerBlocks'] = self::annotate_nodes(
					$block['innerBlocks'],
					$document_token,
					$ordinal
				);
			}
		}

		return $blocks;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Working tree.
	 */
	private static function path_for_handle( array $blocks, string $handle, string $prefix = '' ): ?string {
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$path = '' === $prefix ? (string) $index : $prefix . '.' . $index;

			if ( isset( $block[ self::INTERNAL_HANDLE ] ) && $handle === $block[ self::INTERNAL_HANDLE ] ) {
				return $path;
			}

			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$found = self::path_for_handle( $block['innerBlocks'], $handle, $path );

				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Working tree.
	 * @return array<int, array<string, mixed>>
	 */
	private static function replace_block( array $blocks, string $path, array $replacement ): array {
		$indexes = array_map( 'intval', explode( '.', $path ) );

		return self::replace_at_indexes( $blocks, $indexes, $replacement );
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Working tree.
	 * @param array<int, int>                  $indexes Numeric path indexes.
	 * @return array<int, array<string, mixed>>
	 */
	private static function replace_at_indexes( array $blocks, array $indexes, array $replacement ): array {
		$index = array_shift( $indexes );

		if ( null === $index || ! isset( $blocks[ $index ] ) ) {
			return $blocks;
		}

		if ( array() === $indexes ) {
			$blocks[ $index ] = $replacement;
			return $blocks;
		}

		$children = isset( $blocks[ $index ]['innerBlocks'] ) && is_array( $blocks[ $index ]['innerBlocks'] )
			? $blocks[ $index ]['innerBlocks']
			: array();

		$blocks[ $index ]['innerBlocks'] = self::replace_at_indexes(
			$children,
			$indexes,
			$replacement
		);

		return $blocks;
	}

	/**
	 * @param array<string, mixed> $descriptor Runtime module descriptor.
	 * @return array<string, mixed>|null
	 */
	private static function parameter( array $descriptor, string $semantic_path ): ?array {
		$parameters = isset( $descriptor['parameters'] ) && is_array( $descriptor['parameters'] )
			? $descriptor['parameters']
			: array();

		foreach ( $parameters as $parameter ) {
			if ( is_array( $parameter )
				&& isset( $parameter['semantic_path'] )
				&& $semantic_path === $parameter['semantic_path']
			) {
				return $parameter;
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $parameter Runtime parameter descriptor.
	 */
	private static function base_value_path( array $parameter ): string {
		$native_path = isset( $parameter['native_path'] ) && is_string( $parameter['native_path'] )
			? $parameter['native_path']
			: '';

		$default_value = $parameter['default'] ?? null;

		if ( is_array( $default_value ) && isset( $default_value['desktop'] ) ) {
			$desktop = $default_value['desktop'];

			if ( is_array( $desktop ) && array_key_exists( 'value', $desktop ) ) {
				return $native_path . '.desktop.value';
			}

			return $native_path . '.desktop';
		}

		return $native_path;
	}

	/**
	 * @param array<string, mixed> $parameter Runtime parameter descriptor.
	 */
	private static function responsive_value_path( array $parameter, string $device ): ?string {
		$native_path = isset( $parameter['native_path'] ) && is_string( $parameter['native_path'] )
			? $parameter['native_path']
			: '';

		$default_value = $parameter['default'] ?? null;

		if ( ! is_array( $default_value ) || ! array_key_exists( $device, $default_value ) ) {
			return null;
		}

		$device_value = $default_value[ $device ];

		if ( is_array( $device_value ) && array_key_exists( 'value', $device_value ) ) {
			return $native_path . '.' . $device . '.value';
		}

		return $native_path . '.' . $device;
	}

	/**
	 * @param mixed                $value Runtime semantic value.
	 * @param array<string, mixed> $parameter Runtime parameter descriptor.
	 * @return array<string, mixed>|null
	 */
	private static function validate_value( $value, array $parameter ): ?array {
		$type = isset( $parameter['type'] ) && is_string( $parameter['type'] )
			? $parameter['type']
			: 'unknown';

		if ( ! self::value_matches_type( $value, $type ) ) {
			return array(
				'expected' => $type,
				'message'  => 'The supplied value does not match the runtime parameter type.',
			);
		}

		$enum = isset( $parameter['enum'] ) && is_array( $parameter['enum'] )
			? $parameter['enum']
			: array();

		if ( array() !== $enum && ! in_array( $value, $enum, true ) ) {
			return array(
				'expected' => $enum,
				'message'  => 'The supplied value is not one of the runtime enum values.',
			);
		}

		$constraints = isset( $parameter['constraints'] ) && is_array( $parameter['constraints'] )
			? $parameter['constraints']
			: array();

		return self::validate_constraints( $value, $constraints );
	}

	/**
	 * @param mixed                $value Runtime semantic value.
	 * @param array<string, mixed> $constraints Runtime constraints.
	 * @return array<string, mixed>|null
	 */
	private static function validate_constraints( $value, array $constraints ): ?array {
		if ( is_numeric( $value ) ) {
			if ( isset( $constraints['minimum'] ) && $value < $constraints['minimum'] ) {
				return array(
					'expected' => array( 'minimum' => $constraints['minimum'] ),
					'message'  => 'The supplied value is below the runtime minimum.',
				);
			}

			if ( isset( $constraints['maximum'] ) && $value > $constraints['maximum'] ) {
				return array(
					'expected' => array( 'maximum' => $constraints['maximum'] ),
					'message'  => 'The supplied value is above the runtime maximum.',
				);
			}
		}

		if ( ! is_string( $value ) ) {
			return null;
		}

		if ( isset( $constraints['minLength'] ) && strlen( $value ) < (int) $constraints['minLength'] ) {
			return array(
				'expected' => array( 'minLength' => $constraints['minLength'] ),
				'message'  => 'The supplied value is shorter than the runtime minimum length.',
			);
		}

		if ( isset( $constraints['maxLength'] ) && strlen( $value ) > (int) $constraints['maxLength'] ) {
			return array(
				'expected' => array( 'maxLength' => $constraints['maxLength'] ),
				'message'  => 'The supplied value exceeds the runtime maximum length.',
			);
		}

		if ( isset( $constraints['pattern'] ) && is_string( $constraints['pattern'] ) ) {
			$pattern = '~' . str_replace( '~', '\\~', $constraints['pattern'] ) . '~';

			if ( 1 !== preg_match( $pattern, $value ) ) {
				return array(
					'expected' => array( 'pattern' => $constraints['pattern'] ),
					'message'  => 'The supplied value does not match the runtime pattern.',
				);
			}
		}

		return null;
	}

	/**
	 * @param mixed $value Runtime semantic value.
	 */
	private static function value_matches_type( $value, string $type ): bool {
		switch ( $type ) {
			case 'string':
			case 'text':
			case 'html':
			case 'url':
			case 'divi/richtext':
				return is_string( $value );

			case 'integer':
				return is_int( $value );

			case 'number':
				return is_int( $value ) || is_float( $value );

			case 'boolean':
				return is_bool( $value );

			case 'array':
			case 'object':
				return is_array( $value );

			case 'null':
				return null === $value;

			case 'unknown':
			default:
				return true;
		}
	}

	/**
	 * @param array<string, mixed> $values Existing values.
	 * @param mixed                $value New value.
	 * @return array<string, mixed>
	 */
	private static function set_path_value( array $values, string $path, $value ): array {
		$segments = array_values( array_filter( explode( '.', $path ), 'strlen' ) );

		if ( array() === $segments ) {
			return $values;
		}

		$segment = array_shift( $segments );

		if ( array() === $segments ) {
			$values[ $segment ] = $value;
			return $values;
		}

		$child = isset( $values[ $segment ] ) && is_array( $values[ $segment ] )
			? $values[ $segment ]
			: array();

		$values[ $segment ] = self::set_path_value(
			$child,
			implode( '.', $segments ),
			$value
		);

		return $values;
	}

	/**
	 * @param array<string, array<string, mixed>> $descriptors Runtime descriptors.
	 */
	private static function allows_child( string $parent_name, string $child_name, array $descriptors ): bool {
		if ( 'divi/placeholder' === $parent_name ) {
			return 'divi/section' === $child_name;
		}

		if ( 'divi/section' === $parent_name ) {
			return 'divi/row' === $child_name;
		}

		if ( 'divi/row' === $parent_name ) {
			return 'divi/column' === $child_name;
		}

		if ( 'divi/column' === $parent_name ) {
			if ( ! isset( $descriptors[ $child_name ] ) ) {
				return false;
			}

			if ( in_array( $child_name, array( 'divi/section', 'divi/row', 'divi/column' ), true ) ) {
				return false;
			}

			$parents = self::string_list( $descriptors[ $child_name ], 'parent' );

			return array() === $parents || in_array( $parent_name, $parents, true );
		}

		if ( ! isset( $descriptors[ $parent_name ] ) || ! isset( $descriptors[ $child_name ] ) ) {
			return false;
		}

		$allowed = self::string_list( $descriptors[ $parent_name ], 'allowed_children' );

		if ( array() !== $allowed ) {
			return in_array( $child_name, $allowed, true );
		}

		$parents = self::string_list( $descriptors[ $child_name ], 'parent' );

		return array() !== $parents && in_array( $parent_name, $parents, true );
	}

	/**
	 * @param array<string, array<string, mixed>> $descriptors Runtime descriptors.
	 * @return mixed
	 */
	private static function child_expectation( string $parent_name, array $descriptors ) {
		if ( 'divi/placeholder' === $parent_name ) {
			return array( 'divi/section' );
		}

		if ( 'divi/section' === $parent_name ) {
			return array( 'divi/row' );
		}

		if ( 'divi/row' === $parent_name ) {
			return array( 'divi/column' );
		}

		if ( 'divi/column' === $parent_name ) {
			return 'registered non-structural runtime Divi module compatible with divi/column';
		}

		if ( isset( $descriptors[ $parent_name ] ) ) {
			$allowed = self::string_list( $descriptors[ $parent_name ], 'allowed_children' );

			if ( array() !== $allowed ) {
				return $allowed;
			}
		}

		return 'runtime-declared parent/child relationship';
	}

	/**
	 * @param array<string, mixed> $source Source object.
	 */
	private static function new_handle( array $source, string $document_token, int $operation_index, string $module_name, int $salt = 0 ): string {
		if ( isset( $source['new_handle'] )
			&& is_string( $source['new_handle'] )
			&& 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{2,79}$/', $source['new_handle'] )
		) {
			return $source['new_handle'];
		}

		$seed = $document_token . '|' . $operation_index . '|' . $module_name . '|' . $salt;

		return 'batch-' . substr( hash( 'sha256', $seed ), 0, 20 );
	}

	/**
	 * @param array<string, mixed> $block Block copy.
	 * @param array<int, string>   $created Created handles.
	 * @return array<string, mixed>
	 */
	private static function refresh_subtree_handles( array $block, string $root_handle, string $document_token, int $operation_index, int &$seed, array &$created ): array {
		if ( 0 === $seed ) {
			$handle = $root_handle;
		} else {
			$handle_seed = $document_token . '|' . $operation_index . '|copy|' . $seed;
			$handle      = 'batch-' . substr( hash( 'sha256', $handle_seed ), 0, 20 );
		}

		$block[ self::INTERNAL_HANDLE ] = $handle;

		$created[] = $handle;

		++$seed;

		if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $index => $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}

				$block['innerBlocks'][ $index ] = self::refresh_subtree_handles(
					$child,
					$root_handle,
					$document_token,
					$operation_index,
					$seed,
					$created
				);
			}
		}

		return $block;
	}

	/**
	 * @return array<int, string|null>
	 */
	private static function child_slots( int $count ): array {
		$slots = array( "\n" );

		for ( $index = 0; $index < $count; ++$index ) {
			$slots[] = null;
			$slots[] = "\n";
		}

		return $slots;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Working tree.
	 * @return array<int, array<string, mixed>>
	 */
	private static function strip_internal_handles( array $blocks ): array {
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			unset( $block[ self::INTERNAL_HANDLE ] );

			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::strip_internal_handles( $block['innerBlocks'] );
			}

			$blocks[ $index ] = $block;
		}

		return $blocks;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function runtime_descriptor_map(): array {
		$catalog = RuntimeModuleRegistry::catalog();

		$modules = isset( $catalog['modules'] ) && is_array( $catalog['modules'] )
			? $catalog['modules']
			: array();

		$map = array();

		foreach ( $modules as $module ) {
			if ( ! isset( $module['name'] ) || ! is_string( $module['name'] ) ) {
				continue;
			}

			$descriptor = RuntimeModuleRegistry::describe( $module['name'] );

			if ( ! empty( $descriptor['success'] ) ) {
				$map[ $module['name'] ] = $descriptor;
			}
		}

		return $map;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function load_state( int $post_id, string $document_token ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array(
				'error' => self::failure(
					$post_id,
					'post_not_found',
					'The requested post does not exist.'
				),
			);
		}

		$content = (string) $post->post_content;

		$current_token = hash( 'sha256', $post_id . '|' . $content );

		if ( ! hash_equals( $current_token, $document_token ) ) {
			$stale_error = self::error(
				'stale_document_token',
				null,
				null,
				'document_token',
				$document_token,
				$current_token,
				'The persisted document changed after the supplied snapshot was read.'
			);

			return array(
				'error' => array(
					'success'        => false,
					'post_id'        => $post_id,
					'document_token' => $current_token,
					'errors'         => array( $stale_error ),
					'error_code'     => 'stale_document_token',
					'error_message'  => 'The document token is stale. Read the document again before mutating it.',
				),
			);
		}

		return array(
			'post'   => $post,
			'blocks' => parse_blocks( $content ),
		);
	}

	/**
	 * @param array<string, mixed> $operation Operation.
	 * @return array<string, mixed>
	 */
	private static function unsupported_semantic_operation( int $index, array $operation, string $code, string $message ): array {
		$property = isset( $operation['property'] ) && is_string( $operation['property'] )
			? $operation['property']
			: null;

		return self::error_result(
			$code,
			$index,
			self::string_field( $operation, 'handle' ),
			$property,
			$operation,
			'runtime-proven writable semantic mapping',
			$message
		);
	}

	/**
	 * @param mixed $offending_value Offending value.
	 * @param mixed $expected Expected value/schema.
	 * @return array<string, mixed>
	 */
	private static function error_result( string $code, ?int $operation_index, ?string $handle, ?string $property, $offending_value, $expected, string $message ): array {
		return array(
			'error' => self::error(
				$code,
				$operation_index,
				$handle,
				$property,
				$offending_value,
				$expected,
				$message
			),
		);
	}

	/**
	 * @param mixed $offending_value Offending value.
	 * @param mixed $expected Expected value/schema.
	 * @return array<string, mixed>
	 */
	private static function error( string $code, ?int $operation_index, ?string $handle, ?string $property, $offending_value, $expected, string $message ): array {
		return array(
			'code'            => $code,
			'operation_index' => $operation_index,
			'node'            => $handle,
			'property'        => $property,
			'offending_value' => $offending_value,
			'expected'        => $expected,
			'message'         => $message,
		);
	}

	/**
	 * @param array<string, mixed> $value_error Value validation error.
	 * @return array<string, mixed>
	 */
	private static function invalid_value_error( int $index, string $handle, string $property, $value, array $value_error ): array {
		return self::error_result(
			'invalid_property_value',
			$index,
			$handle,
			$property,
			$value,
			$value_error['expected'],
			$value_error['message']
		);
	}

	/**
	 * @param mixed $value Offending value.
	 * @return array<string, mixed>
	 */
	private static function unknown_property_error( int $index, string $handle, string $property, $value ): array {
		return self::error_result(
			'property_not_in_runtime_schema',
			$index,
			$handle,
			$property,
			$value,
			'semantic path exposed by divi-module-describe',
			'The property is not present in the runtime parameter graph.'
		);
	}

	/**
	 * @param array<string, array<string, mixed>> $descriptors Runtime descriptors.
	 * @return array<string, mixed>
	 */
	private static function hierarchy_error( int $index, ?string $handle, string $parent_name, array $descriptors ): array {
		return self::error_result(
			'invalid_parent_child',
			$index,
			$handle,
			'parent',
			$parent_name,
			self::child_expectation( $parent_name, $descriptors ),
			'The destination module does not accept this child module type.'
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function structure_error( int $index, string $handle, int $child_index, Throwable $throwable ): array {
		return self::error_result(
			'invalid_structure_operation',
			$index,
			$handle,
			'index',
			$child_index,
			'valid destination child index',
			$throwable->getMessage()
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function failure( int $post_id, string $code, string $message ): array {
		return array(
			'success'       => false,
			'post_id'       => $post_id,
			'persisted'     => false,
			'error_code'    => $code,
			'error_message' => $message,
		);
	}

	/**
	 * @param array<string, mixed> $source Source array.
	 */
	private static function string_field( array $source, string $field ): string {
		return isset( $source[ $field ] ) && is_string( $source[ $field ] )
			? $source[ $field ]
			: '';
	}

	/**
	 * @param array<string, mixed> $block Block.
	 */
	private static function block_name( array $block ): string {
		return isset( $block['blockName'] ) && is_string( $block['blockName'] )
			? $block['blockName']
			: '';
	}

	/**
	 * @param array<string, mixed> $block Block.
	 * @return array<string, mixed>
	 */
	private static function block_attrs( array $block ): array {
		return isset( $block['attrs'] ) && is_array( $block['attrs'] )
			? $block['attrs']
			: array();
	}

	/**
	 * @param array<string, mixed> $source Source array.
	 * @return array<int, string>
	 */
	private static function string_list( array $source, string $field ): array {
		if ( ! isset( $source[ $field ] ) || ! is_array( $source[ $field ] ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$source[ $field ],
				static function ( $value ): bool {
					return is_string( $value ) && '' !== $value;
				}
			)
		);
	}

	/**
	 * @param array<string, mixed> $operation Operation.
	 */
	private static function child_index( array $operation ): int {
		return isset( $operation['index'] ) && is_int( $operation['index'] )
			? $operation['index']
			: -1;
	}

	private static function is_write_status( string $status ): bool {
		return in_array( $status, array( 'draft', 'pending', 'auto-draft' ), true );
	}

	private static function save_revision( int $post_id ): ?int {
		if ( ! function_exists( 'wp_save_post_revision' ) ) {
			return null;
		}

		$revision_id = wp_save_post_revision( $post_id );

		return is_int( $revision_id ) && $revision_id > 0 ? $revision_id : null;
	}

	private function __construct() {
	}
}
