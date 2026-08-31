<?php
/**
 * Schema/runtime-validated generic native Divi document writer.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

final class RuntimeNativeWriter {
	private const CUSTOM_CLASS_ID_PATH = 'module.advanced.htmlAttributes';
	private const CUSTOM_ATTRIBUTE_PATH = 'module.advanced.attributes';
	private const MODULE_PRESET_PATH = 'module.meta.modulePreset';

	/**
	 * Dry-run a raw-native/runtime-derived mutation batch.
	 *
	 * @param array<int, array<string, mixed>> $operations Operations.
	 * @return array<string, mixed>
	 */
	public static function validate( int $post_id, string $document_token, array $operations ): array {
		$state = self::load_state( $post_id, $document_token );
		if ( isset( $state['error'] ) ) {
			return $state['error'];
		}

		$descriptors = self::descriptor_map();
		$breakpoints = RuntimeRegistryDiscovery::breakpoint_names();
		$plan        = self::plan( $state['blocks'], $document_token, $operations, $descriptors, $breakpoints );
		$status      = (string) $state['post']->post_status;

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
				'Generic native mutation persistence is restricted to draft, pending, or auto-draft posts.'
			);
		}

		return $plan;
	}

	/**
	 * Atomically validate and persist a native mutation batch.
	 *
	 * @param array<int, array<string, mixed>> $operations Operations.
	 * @return array<string, mixed>
	 */
	public static function mutate( int $post_id, string $document_token, array $operations ): array {
		if ( ! LayoutManager::is_native_authoring_available() ) {
			return self::failure( $post_id, 'divi5_native_authoring_unavailable', 'Divi 5 native block APIs are not available on this site.' );
		}

		$state = self::load_state( $post_id, $document_token );
		if ( isset( $state['error'] ) ) {
			return $state['error'];
		}

		$status = (string) $state['post']->post_status;
		if ( ! self::is_write_status( $status ) ) {
			return self::failure( $post_id, 'draft_required', 'Generic native mutations are restricted to draft, pending, or auto-draft posts.' );
		}

		$plan = self::plan(
			$state['blocks'],
			$document_token,
			$operations,
			self::descriptor_map(),
			RuntimeRegistryDiscovery::breakpoint_names()
		);

		if ( empty( $plan['valid'] ) ) {
			return array(
				'success'       => false,
				'post_id'       => $post_id,
				'valid'         => false,
				'persisted'     => false,
				'errors'        => $plan['errors'],
				'error_code'    => 'batch_validation_failed',
				'error_message' => 'The native mutation batch is invalid. No operation was persisted.',
			);
		}

		$revision_id = self::save_revision( $post_id );
		$serialized  = serialize_blocks( $plan['blocks'] );
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

		$document = DocumentModel::get( $post_id, true );
		$document['valid']        = true;
		$document['persisted']    = true;
		$document['revision_id']  = $revision_id;
		$document['operations']   = $plan['operations'];
		$document['write_method'] = 'schema-validated-generic-native-mutation';

		return $document;
	}

	/**
	 * Pure mutation planner used by tests and the persistence path.
	 *
	 * @param array<int, array<string, mixed>>    $blocks Parsed blocks.
	 * @param array<int, array<string, mixed>>    $operations Operations.
	 * @param array<string, array<string, mixed>> $descriptors Module descriptors.
	 * @param array<int, string>                  $breakpoints Runtime breakpoints.
	 * @return array<string, mixed>
	 */
	public static function plan( array $blocks, string $document_token, array $operations, array $descriptors, array $breakpoints = array() ): array {
		$working = $blocks;
		$errors  = array();
		$results = array();

		if ( array() === $operations ) {
			$errors[] = self::error( 'empty_batch', null, null, null, null, 'one or more operations', 'Native mutation batches must contain at least one operation.' );
		}

		foreach ( $operations as $index => $operation ) {
			if ( ! is_array( $operation ) ) {
				$errors[] = self::error( 'invalid_operation', (int) $index, null, null, $operation, 'object', 'Each operation must be an object.' );
				continue;
			}

			$result = self::apply_operation( $working, $document_token, $operation, (int) $index, $descriptors, $breakpoints );
			if ( isset( $result['error'] ) ) {
				$errors[] = $result['error'];
				continue;
			}

			$working   = $result['blocks'];
			$results[] = $result['result'];
		}

		return array(
			'success'        => true,
			'valid'          => array() === $errors,
			'persisted'      => false,
			'document_token' => $document_token,
			'operations'     => $results,
			'errors'         => $errors,
			'blocks'         => $working,
			'error_code'     => null,
			'error_message'  => null,
		);
	}

	/**
	 * @param array<int, array<string, mixed>>    $blocks Blocks.
	 * @param array<string, mixed>                $operation Operation.
	 * @param array<string, array<string, mixed>> $descriptors Descriptors.
	 * @param array<int, string>                  $breakpoints Breakpoints.
	 * @return array<string, mixed>
	 */
	private static function apply_operation( array $blocks, string $document_token, array $operation, int $index, array $descriptors, array $breakpoints ): array {
		$op = self::string_field( $operation, 'op' );
		if ( ! in_array( $op, array( 'set', 'unset', 'attribute', 'responsive', 'state', 'preset' ), true ) ) {
			return self::error_result( 'unsupported_operation', $index, null, 'op', $op, array( 'set', 'unset', 'attribute', 'responsive', 'state', 'preset' ), 'Unsupported generic native operation.' );
		}

		$handle   = self::string_field( $operation, 'handle' );
		$resolved = self::resolve_module( $blocks, $document_token, $handle, $index, $descriptors );
		if ( isset( $resolved['error'] ) ) {
			return $resolved;
		}

		$descriptor      = $resolved['descriptor'];
		$block           = $resolved['block'];
		$native_path     = '';
		$value           = null;
		$parameter       = null;
		$validation_level = 'runtime-path-evidence';

		switch ( $op ) {
			case 'set':
				$native_path = self::string_field( $operation, 'native_path' );
				if ( ! array_key_exists( 'value', $operation ) ) {
					return self::error_result( 'missing_value', $index, $handle, $native_path, null, 'native value', 'Set operations require a value.' );
				}
				$value = $operation['value'];
				break;

			case 'unset':
				$native_path = self::string_field( $operation, 'native_path' );
				break;

			case 'attribute':
				$target = self::string_field( $operation, 'target' );
				if ( '' === $target ) {
					$target = 'module-wrapper';
				}
				if ( 'module-wrapper' !== $target ) {
					return self::error_result( 'attribute_target_unavailable', $index, $handle, 'target', $target, 'module-wrapper', 'Sub-element Custom Attribute targeting requires a runtime-proven target registry; use raw-native set only when its native path is discovered.' );
				}
				$name = self::string_field( $operation, 'name' );
				if ( ! self::valid_attribute_name( $name ) ) {
					return self::error_result( 'invalid_attribute_name', $index, $handle, 'name', $name, 'safe HTML attribute name excluding event-handler attributes', 'The requested Custom Attribute name is invalid or unsafe.' );
				}
				$breakpoint = self::breakpoint( $operation, $breakpoints );
				if ( isset( $breakpoint['error'] ) ) {
					return self::error_result( 'breakpoint_unavailable', $index, $handle, 'breakpoint', $breakpoint['value'], $breakpoints, 'The requested breakpoint is not exposed by the active Divi runtime.' );
				}
				$base_path   = in_array( $name, array( 'class', 'id' ), true ) ? self::CUSTOM_CLASS_ID_PATH : self::CUSTOM_ATTRIBUTE_PATH;
				$native_path = $base_path . '.' . $breakpoint['value'] . '.value.' . $name;
				if ( array_key_exists( 'value', $operation ) ) {
					$value = $operation['value'];
					if ( ! is_string( $value ) ) {
						return self::error_result( 'invalid_attribute_value', $index, $handle, $name, $value, 'string', 'Custom Attribute values must be strings.' );
					}
				} else {
					$op = 'unset';
				}
				$validation_level = 'divi-custom-attributes-adapter';
				break;

			case 'responsive':
				$property  = self::string_field( $operation, 'property' );
				$parameter = self::parameter( $descriptor, $property );
				if ( null === $parameter ) {
					return self::error_result( 'property_not_in_runtime_schema', $index, $handle, $property, $operation['value'] ?? null, 'runtime parameter path', 'The responsive property is not present in the runtime parameter graph.' );
				}
				if ( ! array_key_exists( 'value', $operation ) ) {
					return self::error_result( 'missing_value', $index, $handle, $property, null, 'responsive value', 'Responsive operations require a value.' );
				}
				$breakpoint = self::breakpoint( $operation, $breakpoints );
				if ( isset( $breakpoint['error'] ) ) {
					return self::error_result( 'breakpoint_unavailable', $index, $handle, 'breakpoint', $breakpoint['value'], $breakpoints, 'The requested breakpoint is not exposed by the active Divi runtime.' );
				}
				$base = self::parameter_base_path( $parameter );
				if ( '' === $base ) {
					return self::error_result( 'native_mapping_unavailable', $index, $handle, $property, null, 'runtime native path or runtime hint', 'The runtime parameter does not expose enough path evidence for responsive authoring.' );
				}
				$native_path = $base . '.' . $breakpoint['value'] . '.value';
				$value       = $operation['value'];
				break;

			case 'state':
				$property  = self::string_field( $operation, 'property' );
				$state     = self::string_field( $operation, 'state' );
				$parameter = self::parameter( $descriptor, $property );
				if ( null === $parameter ) {
					return self::error_result( 'property_not_in_runtime_schema', $index, $handle, $property, $operation['value'] ?? null, 'runtime parameter path', 'The state property is not present in the runtime parameter graph.' );
				}
				if ( ! array_key_exists( 'value', $operation ) ) {
					return self::error_result( 'missing_value', $index, $handle, $property, null, 'state value', 'State operations require a value.' );
				}
				if ( ! self::state_supported( $state, $parameter ) ) {
					return self::error_result( 'state_unavailable', $index, $handle, 'state', $state, self::parameter_states( $parameter ), 'The runtime parameter metadata does not expose the requested state.' );
				}
				$breakpoint = self::breakpoint( $operation, $breakpoints );
				if ( isset( $breakpoint['error'] ) ) {
					return self::error_result( 'breakpoint_unavailable', $index, $handle, 'breakpoint', $breakpoint['value'], $breakpoints, 'The requested breakpoint is not exposed by the active Divi runtime.' );
				}
				$base = self::parameter_base_path( $parameter );
				if ( '' === $base ) {
					return self::error_result( 'native_mapping_unavailable', $index, $handle, $property, null, 'runtime native path or runtime hint', 'The runtime parameter does not expose enough path evidence for state authoring.' );
				}
				$native_path = $base . '.' . $breakpoint['value'] . '.' . $state;
				$value       = $operation['value'];
				break;

			case 'preset':
				if ( ! array_key_exists( 'preset_id', $operation ) || ! is_string( $operation['preset_id'] ) || '' === $operation['preset_id'] ) {
					return self::error_result( 'invalid_preset_id', $index, $handle, 'preset_id', $operation['preset_id'] ?? null, 'non-empty preset identifier or default', 'Preset operations require a preset identifier.' );
				}
				$native_path      = self::MODULE_PRESET_PATH;
				$value            = $operation['preset_id'];
				$validation_level = 'divi-module-preset-adapter';
				break;
		}

		if ( ! self::valid_native_path( $native_path ) ) {
			return self::error_result( 'invalid_native_path', $index, $handle, 'native_path', $native_path, 'dot-separated native attribute path', 'The native path is invalid.' );
		}

		$path_validation = self::validate_native_path( $native_path, $descriptor, in_array( $validation_level, array( 'divi-custom-attributes-adapter', 'divi-module-preset-adapter' ), true ) );
		if ( isset( $path_validation['error'] ) ) {
			return self::error_result( 'native_path_not_runtime_proven', $index, $handle, 'native_path', $native_path, $path_validation['expected'], $path_validation['error'] );
		}

		if ( 'unset' !== $op ) {
			$value_error = null !== $parameter ? self::validate_parameter_value( $value, $parameter ) : self::validate_exact_root_value( $native_path, $value, $descriptor );
			if ( null !== $value_error ) {
				return self::error_result( 'invalid_native_value', $index, $handle, $native_path, $value, $value_error['expected'], $value_error['message'] );
			}
		}

		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$attrs = 'unset' === $op ? self::unset_path_value( $attrs, $native_path ) : self::set_path_value( $attrs, $native_path, $value );
		$block['attrs'] = $attrs;
		$blocks = self::replace_block( $blocks, $resolved['path'], $block );

		return array(
			'blocks' => $blocks,
			'result' => array(
				'op'               => $op,
				'handle'           => $handle,
				'native_path'      => $native_path,
				'validation_level' => $validation_level,
				'path_evidence'    => $path_validation['evidence'],
			),
		);
	}

	/**
	 * @param array<string, mixed> $descriptor Descriptor.
	 * @return array<string, mixed>|null
	 */
	private static function parameter( array $descriptor, string $semantic_path ): ?array {
		$parameters = isset( $descriptor['parameter_graph'] ) && is_array( $descriptor['parameter_graph'] ) ? $descriptor['parameter_graph'] : array();
		foreach ( $parameters as $parameter ) {
			if ( is_array( $parameter ) && isset( $parameter['semantic_path'] ) && $semantic_path === $parameter['semantic_path'] ) {
				return $parameter;
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $parameter Parameter.
	 */
	private static function parameter_base_path( array $parameter ): string {
		foreach ( array( 'native_path', 'runtime_hint', 'semantic_path' ) as $field ) {
			if ( isset( $parameter[ $field ] ) && is_string( $parameter[ $field ] ) && self::valid_native_path( $parameter[ $field ] ) ) {
				return $parameter[ $field ];
			}
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $parameter Parameter.
	 */
	private static function state_supported( string $state, array $parameter ): bool {
		if ( 'hover' === $state && isset( $parameter['hover'] ) && 'supported' === $parameter['hover'] ) {
			return true;
		}
		if ( 'sticky' === $state && isset( $parameter['sticky'] ) && 'supported' === $parameter['sticky'] ) {
			return true;
		}
		$states = isset( $parameter['states'] ) && is_array( $parameter['states'] ) ? $parameter['states'] : array();
		return in_array( $state, $states, true );
	}

	/**
	 * @param array<string, mixed> $parameter Parameter.
	 * @return array<int, string>
	 */
	private static function parameter_states( array $parameter ): array {
		$states = isset( $parameter['states'] ) && is_array( $parameter['states'] ) ? $parameter['states'] : array();
		if ( isset( $parameter['hover'] ) && 'supported' === $parameter['hover'] ) {
			$states[] = 'hover';
		}
		if ( isset( $parameter['sticky'] ) && 'supported' === $parameter['sticky'] ) {
			$states[] = 'sticky';
		}
		return array_values( array_unique( $states ) );
	}

	/**
	 * @param array<string, mixed> $operation Operation.
	 * @param array<int, string>   $breakpoints Breakpoints.
	 * @return array<string, mixed>
	 */
	private static function breakpoint( array $operation, array $breakpoints ): array {
		$value = self::string_field( $operation, 'breakpoint' );
		if ( '' === $value ) {
			$value = in_array( 'desktop', $breakpoints, true ) || array() === $breakpoints ? 'desktop' : (string) reset( $breakpoints );
		}
		if ( array() !== $breakpoints && ! in_array( $value, $breakpoints, true ) ) {
			return array( 'error' => true, 'value' => $value );
		}
		return array( 'value' => $value );
	}

	/**
	 * @param array<string, mixed> $descriptor Descriptor.
	 * @return array<string, mixed>
	 */
	private static function validate_native_path( string $path, array $descriptor, bool $adapter_path ): array {
		$attributes = isset( $descriptor['raw_runtime']['attributes'] ) && is_array( $descriptor['raw_runtime']['attributes'] ) ? $descriptor['raw_runtime']['attributes'] : array();
		$segments   = explode( '.', $path );
		$root       = (string) reset( $segments );

		if ( ! isset( $attributes[ $root ] ) || ! is_array( $attributes[ $root ] ) ) {
			return array( 'error' => 'The top-level native attribute is not declared by the registered WordPress block schema.', 'expected' => array_keys( $attributes ) );
		}

		if ( 1 === count( $segments ) ) {
			return array( 'evidence' => 'exact top-level WordPress block attribute schema' );
		}

		$root_type = isset( $attributes[ $root ]['type'] ) && is_string( $attributes[ $root ]['type'] ) ? $attributes[ $root ]['type'] : 'unknown';
		if ( 'object' !== $root_type ) {
			return array( 'error' => 'Nested native paths are only allowed beneath a runtime attribute declared as an object.', 'expected' => array( 'root_type' => 'object' ) );
		}

		if ( $adapter_path ) {
			return array( 'evidence' => 'declared object root plus version-independent Divi adapter contract' );
		}

		$parameters = isset( $descriptor['parameter_graph'] ) && is_array( $descriptor['parameter_graph'] ) ? $descriptor['parameter_graph'] : array();
		foreach ( $parameters as $parameter ) {
			if ( ! is_array( $parameter ) ) {
				continue;
			}
			$candidates = array();
			foreach ( array( 'native_path', 'runtime_hint', 'semantic_path' ) as $field ) {
				if ( isset( $parameter[ $field ] ) && is_string( $parameter[ $field ] ) && '' !== $parameter[ $field ] ) {
					$candidates[] = $parameter[ $field ];
				}
			}
			if ( isset( $parameter['native_value_paths'] ) && is_array( $parameter['native_value_paths'] ) ) {
				foreach ( $parameter['native_value_paths'] as $candidate ) {
					if ( is_string( $candidate ) && '' !== $candidate ) {
						$candidates[] = $candidate;
					}
				}
			}

			foreach ( array_unique( $candidates ) as $candidate ) {
				if ( $path === $candidate || 0 === strpos( $path, $candidate . '.' ) || 0 === strpos( $candidate, $path . '.' ) ) {
					return array( 'evidence' => 'runtime parameter graph path: ' . $candidate );
				}
			}
		}

		return array(
			'error'    => 'The path is beneath a declared object attribute, but no runtime parameter path proves this nested location. Discover the module schema first; do not guess paths.',
			'expected' => 'native_path/runtime_hint/native_value_path exposed by divi-module-describe',
		);
	}

	/**
	 * Validate a value against a parameter contract when one is available.
	 *
	 * @param mixed                $value Value.
	 * @param array<string, mixed> $parameter Parameter.
	 * @return array<string, mixed>|null
	 */
	private static function validate_parameter_value( $value, array $parameter ): ?array {
		$type = isset( $parameter['type'] ) && is_string( $parameter['type'] ) ? $parameter['type'] : 'unknown';
		if ( 0 === strpos( $type, 'divi/' ) ) {
			if ( ! is_array( $value ) ) {
				return array( 'expected' => 'object/array for ' . $type, 'message' => 'Complex Divi option-group values must be arrays.' );
			}
		} elseif ( ! self::value_matches_type( $value, $type ) ) {
			return array( 'expected' => $type, 'message' => 'The supplied value does not match the runtime parameter type.' );
		}

		$enum = isset( $parameter['enum'] ) && is_array( $parameter['enum'] ) ? $parameter['enum'] : array();
		if ( array() !== $enum && ! in_array( $value, $enum, true ) ) {
			return array( 'expected' => $enum, 'message' => 'The supplied value is not one of the runtime enum values.' );
		}

		return null;
	}

	/**
	 * Validate exact top-level replacements from the WordPress block schema.
	 *
	 * @param mixed                $value Value.
	 * @param array<string, mixed> $descriptor Descriptor.
	 * @return array<string, mixed>|null
	 */
	private static function validate_exact_root_value( string $path, $value, array $descriptor ): ?array {
		if ( false !== strpos( $path, '.' ) ) {
			return null;
		}
		$schema = isset( $descriptor['raw_runtime']['attributes'][ $path ] ) && is_array( $descriptor['raw_runtime']['attributes'][ $path ] ) ? $descriptor['raw_runtime']['attributes'][ $path ] : array();
		$type   = isset( $schema['type'] ) && is_string( $schema['type'] ) ? $schema['type'] : 'unknown';
		if ( ! self::value_matches_type( $value, $type ) ) {
			return array( 'expected' => $type, 'message' => 'The supplied value does not match the registered WordPress block attribute type.' );
		}
		if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) && ! in_array( $value, $schema['enum'], true ) ) {
			return array( 'expected' => $schema['enum'], 'message' => 'The supplied value is not allowed by the registered block attribute enum.' );
		}
		return null;
	}

	/**
	 * @param mixed $value Value.
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

	private static function valid_native_path( string $path ): bool {
		return '' !== $path && 1 === preg_match( '/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/', $path );
	}

	private static function valid_attribute_name( string $name ): bool {
		if ( '' === $name || 1 !== preg_match( '/^[A-Za-z_:][A-Za-z0-9_:.\-]*$/', $name ) ) {
			return false;
		}
		return 0 !== stripos( $name, 'on' );
	}

	/**
	 * @param array<string, mixed> $values Values.
	 * @param mixed                $value New value.
	 * @return array<string, mixed>
	 */
	private static function set_path_value( array $values, string $path, $value ): array {
		$segments = explode( '.', $path );
		$segment  = array_shift( $segments );
		if ( null === $segment || '' === $segment ) {
			return $values;
		}
		if ( array() === $segments ) {
			$values[ $segment ] = $value;
			return $values;
		}
		$child = isset( $values[ $segment ] ) && is_array( $values[ $segment ] ) ? $values[ $segment ] : array();
		$values[ $segment ] = self::set_path_value( $child, implode( '.', $segments ), $value );
		return $values;
	}

	/**
	 * @param array<string, mixed> $values Values.
	 * @return array<string, mixed>
	 */
	private static function unset_path_value( array $values, string $path ): array {
		$segments = explode( '.', $path );
		$segment  = array_shift( $segments );
		if ( null === $segment || '' === $segment || ! array_key_exists( $segment, $values ) ) {
			return $values;
		}
		if ( array() === $segments ) {
			unset( $values[ $segment ] );
			return $values;
		}
		if ( is_array( $values[ $segment ] ) ) {
			$values[ $segment ] = self::unset_path_value( $values[ $segment ], implode( '.', $segments ) );
			if ( array() === $values[ $segment ] ) {
				unset( $values[ $segment ] );
			}
		}
		return $values;
	}

	/**
	 * Resolve a snapshot handle and module descriptor.
	 *
	 * @param array<int, array<string, mixed>>    $blocks Blocks.
	 * @param array<string, array<string, mixed>> $descriptors Descriptors.
	 * @return array<string, mixed>
	 */
	private static function resolve_module( array $blocks, string $document_token, string $handle, int $index, array $descriptors ): array {
		$map     = array();
		$ordinal = 0;
		self::index_handles( $blocks, $document_token, $map, $ordinal );
		if ( ! isset( $map[ $handle ] ) ) {
			return self::error_result( 'node_not_found', $index, $handle, 'handle', $handle, 'existing snapshot handle', 'The requested node handle does not exist in this document snapshot.' );
		}
		$path  = $map[ $handle ];
		$block = BlockTreeEditor::get( $blocks, $path );
		if ( null === $block ) {
			return self::error_result( 'node_not_found', $index, $handle, 'handle', $handle, 'existing snapshot handle', 'The requested node path could not be resolved.' );
		}
		$name = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : '';
		if ( ! isset( $descriptors[ $name ] ) ) {
			return self::error_result( 'node_not_authorable', $index, $handle, 'module_type', $name, 'registered Divi runtime module', 'The requested node is not an authorable runtime Divi module.' );
		}
		return array( 'path' => $path, 'block' => $block, 'descriptor' => $descriptors[ $name ] );
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @param array<string, string>            $map Handle map.
	 */
	private static function index_handles( array $blocks, string $document_token, array &$map, int &$ordinal, string $prefix = '' ): void {
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) || ! isset( $block['blockName'] ) || ! is_string( $block['blockName'] ) || '' === $block['blockName'] ) {
				continue;
			}
			$path   = '' === $prefix ? (string) $index : $prefix . '.' . $index;
			$handle = DocumentModel::snapshot_handle( $document_token, $ordinal, $block['blockName'] );
			$map[ $handle ] = $path;
			++$ordinal;
			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::index_handles( $block['innerBlocks'], $document_token, $map, $ordinal, $path );
			}
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private static function replace_block( array $blocks, string $path, array $replacement ): array {
		$indexes = array_map( 'intval', explode( '.', $path ) );
		return self::replace_at_indexes( $blocks, $indexes, $replacement );
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @param array<int, int>                  $indexes Path indexes.
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
		$children = isset( $blocks[ $index ]['innerBlocks'] ) && is_array( $blocks[ $index ]['innerBlocks'] ) ? $blocks[ $index ]['innerBlocks'] : array();
		$blocks[ $index ]['innerBlocks'] = self::replace_at_indexes( $children, $indexes, $replacement );
		return $blocks;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function descriptor_map(): array {
		$catalog = RuntimeModuleRegistry::catalog();
		$modules = isset( $catalog['modules'] ) && is_array( $catalog['modules'] ) ? $catalog['modules'] : array();
		$map     = array();
		foreach ( $modules as $module ) {
			if ( ! is_array( $module ) || ! isset( $module['name'] ) || ! is_string( $module['name'] ) ) {
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
			return array( 'error' => self::failure( $post_id, 'post_not_found', 'The requested post does not exist.' ) );
		}
		$content       = (string) $post->post_content;
		$current_token = hash( 'sha256', $post_id . '|' . $content );
		if ( ! hash_equals( $current_token, $document_token ) ) {
			return array(
				'error' => array(
					'success'        => false,
					'post_id'        => $post_id,
					'document_token' => $current_token,
					'persisted'      => false,
					'error_code'     => 'stale_document_token',
					'error_message'  => 'The document token is stale. Read the document again before mutating it.',
				),
			);
		}
		return array( 'post' => $post, 'blocks' => parse_blocks( $content ) );
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

	/**
	 * @param mixed $offending Offending value.
	 * @param mixed $expected Expected value.
	 * @return array<string, mixed>
	 */
	private static function error_result( string $code, ?int $index, ?string $handle, ?string $property, $offending, $expected, string $message ): array {
		return array( 'error' => self::error( $code, $index, $handle, $property, $offending, $expected, $message ) );
	}

	/**
	 * @param mixed $offending Offending value.
	 * @param mixed $expected Expected value.
	 * @return array<string, mixed>
	 */
	private static function error( string $code, ?int $index, ?string $handle, ?string $property, $offending, $expected, string $message ): array {
		return array(
			'code'            => $code,
			'operation_index' => $index,
			'node'            => $handle,
			'property'        => $property,
			'offending_value' => $offending,
			'expected'        => $expected,
			'message'         => $message,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function failure( int $post_id, string $code, string $message ): array {
		return array( 'success' => false, 'post_id' => $post_id, 'persisted' => false, 'error_code' => $code, 'error_message' => $message );
	}

	/**
	 * @param array<string, mixed> $source Source.
	 */
	private static function string_field( array $source, string $field ): string {
		return isset( $source[ $field ] ) && is_string( $source[ $field ] ) ? $source[ $field ] : '';
	}

	private function __construct() {
	}
}
