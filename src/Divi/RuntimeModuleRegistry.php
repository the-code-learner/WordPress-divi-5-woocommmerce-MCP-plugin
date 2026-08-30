<?php
/**
 * Clean-break runtime discovery and normalized module descriptions.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

use Throwable;

final class RuntimeModuleRegistry {
	private const MODULE_REGISTRATION_CLASS = '\\ET\\Builder\\Packages\\ModuleLibrary\\ModuleRegistration';

	private const DIVI_CATEGORIES = array(
		'module',
		'child-module',
		'structure',
		'fullwidth-module',
	);

	private const VALIDATED_VALUE_TYPES = array(
		'string',
		'text',
		'html',
		'url',
		'divi/richtext',
		'integer',
		'number',
		'boolean',
		'array',
		'object',
		'null',
	);

	/**
	 * Return every block type that can be identified as a Divi runtime module.
	 *
	 * @return array<string, mixed>
	 */
	public static function catalog(): array {
		return self::catalog_from_types( self::registered_types() );
	}

	/**
	 * Pure catalog builder used by the runtime and isolated fixtures.
	 *
	 * @param array<string, object> $registered Registered block types.
	 * @return array<string, mixed>
	 */
	public static function catalog_from_types( array $registered ): array {
		$modules = array();

		foreach ( $registered as $name => $type ) {
			if ( ! is_string( $name ) || ! is_object( $type ) || ! self::is_runtime_module( $name, $type ) ) {
				continue;
			}

			$modules[] = self::describe_type( $name, $type, false );
		}

		usort(
			$modules,
			static function ( array $left, array $right ): int {
				return strcmp( (string) $left['name'], (string) $right['name'] );
			}
		);

		return array(
			'success'       => true,
			'module_count'  => count( $modules ),
			'modules'       => $modules,
			'error_code'    => null,
			'error_message' => null,
		);
	}

	/**
	 * Describe one runtime-registered module.
	 *
	 * @return array<string, mixed>
	 */
	public static function describe( string $module_name ): array {
		return self::describe_from_types( $module_name, self::registered_types() );
	}

	/**
	 * Pure descriptor builder used by isolated fixtures.
	 *
	 * @param array<string, object> $registered Registered block types.
	 * @return array<string, mixed>
	 */
	public static function describe_from_types( string $module_name, array $registered ): array {
		$type = $registered[ $module_name ] ?? null;

		if ( ! is_object( $type ) || ! self::is_runtime_module( $module_name, $type ) ) {
			return self::failure( 'module_not_registered', 'The requested module is not exposed as a compatible Divi runtime module on this site.' );
		}

		$result                  = self::describe_type( $module_name, $type, true );
		$result['success']       = true;
		$result['error_code']    = null;
		$result['error_message'] = null;

		return $result;
	}

	/**
	 * Decide whether a registered block type demonstrates Divi-module provenance.
	 *
	 * Core Divi blocks are authoritative. Third-party namespaces are accepted only
	 * when runtime metadata demonstrates a Divi-shaped module registration.
	 */
	public static function is_runtime_module( string $name, object $type ): bool {
		if ( 0 === strpos( $name, 'divi/' ) ) {
			return ! in_array( $name, array( 'divi/placeholder', 'divi/shortcode-module' ), true );
		}

		foreach ( array( 'is_divi_module', 'divi_module', 'diviModule' ) as $property ) {
			if ( isset( $type->{$property} ) && true === $type->{$property} ) {
				return true;
			}
		}

		$attributes = isset( $type->attributes ) && is_array( $type->attributes ) ? $type->attributes : array();
		$category   = isset( $type->category ) && is_string( $type->category ) ? $type->category : '';

		if ( in_array( $category, self::DIVI_CATEGORIES, true ) && isset( $attributes['module'] ) ) {
			return true;
		}

		if ( ! isset( $attributes['module'] ) ) {
			return false;
		}

		$signature = array_intersect( array_keys( $attributes ), array( 'style', 'metadata', 'lock', 'className' ) );

		return array() !== $signature;
	}

	/**
	 * @return array<string, object>
	 */
	private static function registered_types(): array {
		if ( ! class_exists( '\\WP_Block_Type_Registry' ) ) {
			return array();
		}

		$registry = \WP_Block_Type_Registry::get_instance();

		if ( ! is_object( $registry ) || ! method_exists( $registry, 'get_all_registered' ) ) {
			return array();
		}

		$types = $registry->get_all_registered();

		return is_array( $types ) ? $types : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function describe_type( string $name, object $type, bool $include_raw ): array {
		$attributes           = isset( $type->attributes ) && is_array( $type->attributes ) ? $type->attributes : array();
		$supports             = isset( $type->supports ) && is_array( $type->supports ) ? $type->supports : array();
		$defaults             = self::default_attributes( $name, $attributes );
		$allowed_children     = self::allowed_children( $name, $type );
		$provider             = self::provider( $name );
		$parameter_graph      = RuntimeParameterNormalizer::normalize( $attributes, $defaults );
		$authoring_parameters = self::authoring_parameters( $parameter_graph );
		$result               = array(
			'name'                      => $name,
			'title'                     => isset( $type->title ) && is_string( $type->title ) ? $type->title : $name,
			'category'                  => isset( $type->category ) && is_string( $type->category ) ? $type->category : '',
			'provider'                  => $provider,
			'provenance'                => array(
				'source'             => 'wp_block_type_registry',
				'block_namespace'    => $provider['id'],
				'registration_class' => get_class( $type ),
			),
			'compatibility_mode'        => self::compatibility_mode( $name, $type ),
			'introspection'             => array(
				'level'            => array() !== $attributes ? 'runtime-schema' : 'registration-only',
				'parameter_graph'  => array() !== $parameter_graph ? 'available' : 'unavailable',
				'raw_runtime_data' => $include_raw ? 'included' : 'available-on-describe',
			),
			'parent'                    => self::string_list_property( $type, 'parent' ),
			'ancestor'                  => self::string_list_property( $type, 'ancestor' ),
			'allowed_children'          => $allowed_children,
			'parameter_count'           => count( $parameter_graph ),
			'authoring_parameter_count' => count( $authoring_parameters ),
			'parameter_graph'           => $parameter_graph,
			'parameters'                => $authoring_parameters,
			'capabilities'              => self::module_capabilities( $parameter_graph, $allowed_children ),
		);

		if ( $include_raw ) {
			$result['raw_runtime'] = array(
				'attributes'         => $attributes,
				'default_attributes' => $defaults,
				'supports'           => $supports,
				'parent'             => $result['parent'],
				'ancestor'           => $result['ancestor'],
				'allowed_children'   => $allowed_children,
			);
		}

		return $result;
	}

	/**
	 * Keep the mutation-facing parameter list limited to runtime-proven value paths
	 * whose value type can also be validated without guessing.
	 * The complete introspection graph remains available in parameter_graph.
	 *
	 * @param array<int, array<string, mixed>> $parameters Normalized parameters.
	 * @return array<int, array<string, mixed>>
	 */
	private static function authoring_parameters( array $parameters ): array {
		$authoring = array();

		foreach ( $parameters as $parameter ) {
			if ( ! isset( $parameter['write_mapping'] ) || 'supported' !== $parameter['write_mapping'] ) {
				continue;
			}

			if ( ! self::has_validated_value_contract( $parameter ) ) {
				continue;
			}

			$native_path = isset( $parameter['native_path'] ) && is_string( $parameter['native_path'] )
				? $parameter['native_path']
				: '';
			$value_paths = isset( $parameter['native_value_paths'] ) && is_array( $parameter['native_value_paths'] )
				? $parameter['native_value_paths']
				: array();

			if ( '' === $native_path ) {
				continue;
			}

			if ( isset( $value_paths['default'] ) && $native_path === $value_paths['default'] ) {
				$parameter['devices']     = array();
				$parameter['breakpoints'] = array();
				$authoring[]              = $parameter;
				continue;
			}

			$legacy_default = array();
			$device_values  = isset( $parameter['default_by_device'] ) && is_array( $parameter['default_by_device'] )
				? $parameter['default_by_device']
				: array();

			foreach ( array( 'desktop', 'tablet', 'phone' ) as $device ) {
				$expected_path = $native_path . '.' . $device . '.value';

				if ( isset( $value_paths[ $device ] ) && $expected_path === $value_paths[ $device ] && array_key_exists( $device, $device_values ) ) {
					$legacy_default[ $device ] = array( 'value' => $device_values[ $device ] );
				}
			}

			if ( ! isset( $legacy_default['desktop'] ) ) {
				continue;
			}

			$parameter['default']     = $legacy_default;
			$parameter['devices']     = array_values( array_keys( $legacy_default ) );
			$parameter['breakpoints'] = $parameter['devices'];
			$authoring[]              = $parameter;
		}

		return $authoring;
	}

	/**
	 * @param array<string, mixed> $parameter Normalized parameter.
	 */
	private static function has_validated_value_contract( array $parameter ): bool {
		$type = isset( $parameter['type'] ) && is_string( $parameter['type'] )
			? $parameter['type']
			: 'unknown';

		if ( in_array( $type, self::VALIDATED_VALUE_TYPES, true ) ) {
			return true;
		}

		$enum = isset( $parameter['enum'] ) && is_array( $parameter['enum'] )
			? $parameter['enum']
			: array();

		return array() !== $enum;
	}

	/**
	 * @param array<int, array<string, mixed>> $parameters Parameters.
	 * @param array<int, string>               $allowed_children Nested modules.
	 * @return array<string, mixed>
	 */
	private static function module_capabilities( array $parameters, array $allowed_children ): array {
		return array(
			'nested_modules'   => array() !== $allowed_children ? 'supported' : 'unknown',
			'responsive'       => self::aggregate_parameter_status( $parameters, 'responsive' ),
			'hover'            => self::aggregate_parameter_status( $parameters, 'hover' ),
			'sticky'           => self::aggregate_parameter_status( $parameters, 'sticky' ),
			'presets'          => self::aggregate_parameter_status( $parameters, 'preset_support' ),
			'design_variables' => self::aggregate_parameter_status( $parameters, 'design_variable' ),
			'global_values'    => self::aggregate_parameter_status( $parameters, 'global_value_support' ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $parameters Parameters.
	 */
	private static function aggregate_parameter_status( array $parameters, string $key ): string {
		$has_unavailable = false;

		foreach ( $parameters as $parameter ) {
			$status = isset( $parameter[ $key ] ) && is_string( $parameter[ $key ] ) ? $parameter[ $key ] : 'unknown';

			if ( 'supported' === $status ) {
				return 'supported';
			}

			if ( 'unavailable' === $status ) {
				$has_unavailable = true;
			}
		}

		return $has_unavailable ? 'unavailable' : 'unknown';
	}

	/**
	 * @return array<string, string>
	 */
	private static function provider( string $name ): array {
		$parts     = explode( '/', $name, 2 );
		$namespace = isset( $parts[0] ) && '' !== $parts[0] ? $parts[0] : 'unknown';

		return array(
			'id'         => $namespace,
			'provenance' => 'block_namespace',
		);
	}

	private static function compatibility_mode( string $name, object $type ): string {
		foreach ( array( 'compatibility_mode', 'divi_compatibility_mode', 'conversion_mode' ) as $property ) {
			if ( isset( $type->{$property} ) && is_string( $type->{$property} ) ) {
				$value = strtolower( $type->{$property} );

				if ( in_array( $value, array( 'native', 'converted', 'legacy' ), true ) ) {
					return $value;
				}
		}

		return 0 === strpos( $name, 'divi/' ) ? 'native' : 'unknown';
	}

	/**
	 * @return array<int, string>
	 */
	private static function allowed_children( string $name, object $type ): array {
		foreach ( array( 'allowed_blocks', 'childrenName', 'children_name' ) as $property ) {
			$children = self::string_list_property( $type, $property );

			if ( array() !== $children ) {
				return $children;
			}
		}

		if ( 0 === strpos( $name, 'divi/' ) && class_exists( '\\WP_Block_Type_Registry' ) ) {
			$legacy = ModuleRegistry::schema( $name );

			if ( ! empty( $legacy['success'] ) && isset( $legacy['allowed_children'] ) && is_array( $legacy['allowed_children'] ) ) {
				return array_values( $legacy['allowed_children'] );
			}
		}

		return array();
	}

	/**
	 * @param array<string, mixed> $attributes Runtime attribute schema.
	 * @return array<string, mixed>
	 */
	private static function default_attributes( string $module_name, array $attributes ): array {
		$defaults = array();

		foreach ( $attributes as $name => $schema ) {
			if ( is_string( $name ) && is_array( $schema ) && array_key_exists( 'default', $schema ) ) {
				$defaults[ $name ] = $schema['default'];
			}
		}

		$class = self::MODULE_REGISTRATION_CLASS;

		if ( ! class_exists( $class ) || ! method_exists( $class, 'get_default_attrs' ) ) {
			return $defaults;
		}

		try {
			$runtime_defaults = $class::get_default_attrs( $module_name );

			return is_array( $runtime_defaults ) ? array_replace_recursive( $defaults, $runtime_defaults ) : $defaults;
		} catch ( Throwable $throwable ) {
			return $defaults;
		}
	}

	/**
	 * @return array<int, string>
	 */
	private static function string_list_property( object $block_type, string $property ): array {
		if ( ! isset( $block_type->{$property} ) || ! is_array( $block_type->{$property} ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$block_type->{$property},
				static function ( $value ): bool {
					return is_string( $value ) && '' !== $value;
				}
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function failure( string $code, string $message ): array {
		return array(
			'success'       => false,
			'error_code'    => $code,
			'error_message' => $message,
		);
	}

	private function __construct() {
	}
}
