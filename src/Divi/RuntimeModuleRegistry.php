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
		$attributes       = isset( $type->attributes ) && is_array( $type->attributes ) ? $type->attributes : array();
		$supports         = isset( $type->supports ) && is_array( $type->supports ) ? $type->supports : array();
		$defaults         = self::default_attributes( $name, $attributes );
		$allowed_children = self::allowed_children( $name, $type );
		$provider         = self::provider( $name );
		$parameter_graph  = self::parameter_graph( $attributes, $defaults );
		$result           = array(
			'name'               => $name,
			'title'              => isset( $type->title ) && is_string( $type->title ) ? $type->title : $name,
			'category'           => isset( $type->category ) && is_string( $type->category ) ? $type->category : '',
			'provider'           => $provider,
			'provenance'         => array(
				'source'             => 'wp_block_type_registry',
				'block_namespace'    => $provider['id'],
				'registration_class' => get_class( $type ),
			),
			'compatibility_mode' => self::compatibility_mode( $name, $type ),
			'introspection'      => array(
				'level'            => array() !== $attributes ? 'runtime-schema' : 'registration-only',
				'parameter_graph'  => array() !== $parameter_graph ? 'available' : 'unavailable',
				'raw_runtime_data' => $include_raw ? 'included' : 'available-on-describe',
			),
			'parent'             => self::string_list_property( $type, 'parent' ),
			'ancestor'           => self::string_list_property( $type, 'ancestor' ),
			'allowed_children'   => $allowed_children,
			'parameter_count'    => count( $parameter_graph ),
			'parameters'         => $parameter_graph,
			'capabilities'       => self::module_capabilities( $parameter_graph, $allowed_children ),
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
	 * @param array<string, mixed> $attributes Runtime attribute schema.
	 * @param array<string, mixed> $defaults Runtime defaults.
	 * @return array<int, array<string, mixed>>
	 */
	private static function parameter_graph( array $attributes, array $defaults ): array {
		$parameters = array();

		foreach ( $attributes as $name => $schema ) {
			if ( ! is_string( $name ) || ! is_array( $schema ) ) {
				continue;
			}

			$parameters[ $name ] = self::normalize_parameter(
				$name,
				$name,
				$schema,
				self::value_at_path( $defaults, $name ),
				'block_attribute_schema'
			);
			self::discover_attr_names( $schema, $defaults, $parameters );
		}

		ksort( $parameters );

		return array_values( $parameters );
	}

	/**
	 * @param array<string, mixed>                $node Runtime schema fragment.
	 * @param array<string, mixed>                $defaults Runtime defaults.
	 * @param array<string, array<string, mixed>> $parameters Normalized parameters.
	 */
	private static function discover_attr_names( array $node, array $defaults, array &$parameters ): void {
		if ( isset( $node['attrName'] ) && is_string( $node['attrName'] ) && '' !== $node['attrName'] ) {
			$path = $node['attrName'];

			$parameters[ $path ] = self::normalize_parameter(
				$path,
				$path,
				$node,
				self::value_at_path( $defaults, $path ),
				'divi_runtime_settings'
			);
		}

		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				self::discover_attr_names( $value, $defaults, $parameters );
			}
		}
	}

	/**
	 * @param array<string, mixed> $schema Runtime schema fragment.
	 * @param mixed                $default_value Default value.
	 * @return array<string, mixed>
	 */
	private static function normalize_parameter( string $semantic_path, string $native_path, array $schema, $default_value, string $source ): array {
		$devices       = self::discover_keys( $schema, array( 'desktop', 'tablet', 'phone' ) );
		$default_keys  = is_array( $default_value ) ? self::discover_keys( $default_value, array( 'desktop', 'tablet', 'phone' ) ) : array();
		$devices       = array_values( array_unique( array_merge( $devices, $default_keys ) ) );
		$component     = isset( $schema['component'] ) && is_array( $schema['component'] ) ? $schema['component'] : array();
		$features      = isset( $schema['features'] ) && is_array( $schema['features'] ) ? $schema['features'] : array();
		$allowed_units = self::discover_scalar_list( $schema, array( 'allowedUnits', 'allowed_units', 'units' ) );
		$constraints   = array();

		foreach ( array( 'minimum', 'maximum', 'minLength', 'maxLength', 'pattern', 'minItems', 'maxItems' ) as $constraint ) {
			if ( array_key_exists( $constraint, $schema ) ) {
				$constraints[ $constraint ] = $schema[ $constraint ];
			}
		}

		return array(
			'semantic_path'        => $semantic_path,
			'native_path'          => $native_path,
			'native_provenance'    => $source,
			'type'                 => self::infer_type( $schema, $component, $features ),
			'default'              => $default_value,
			'enum'                 => isset( $schema['enum'] ) && is_array( $schema['enum'] ) ? array_values( $schema['enum'] ) : array(),
			'constraints'          => $constraints,
			'allowed_units'        => $allowed_units,
			'responsive'           => array() !== $devices ? 'supported' : 'unknown',
			'devices'              => $devices,
			'breakpoints'          => $devices,
			'hover'                => self::feature_status( $schema, array( 'hover' ) ),
			'sticky'               => self::feature_status( $schema, array( 'sticky' ) ),
			'preset_support'       => self::feature_status( $schema, array( 'preset' ) ),
			'design_variable'      => self::feature_status( $schema, array( 'designVariable', 'designVariables' ) ),
			'global_value_support' => self::feature_status( $schema, array( 'globalValue', 'globalValues' ) ),
		);
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
	 * @param array<string, mixed> $values Values.
	 * @return mixed|null
	 */
	private static function value_at_path( array $values, string $path ) {
		$current = $values;

		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return null;
			}

			$current = $current[ $segment ];
		}

		return $current;
	}

	/**
	 * @param array<string, mixed> $schema Runtime schema fragment.
	 * @param array<string, mixed> $component Component metadata.
	 * @param array<string, mixed> $features Feature metadata.
	 */
	private static function infer_type( array $schema, array $component, array $features ): string {
		if ( isset( $schema['type'] ) && is_string( $schema['type'] ) ) {
			return $schema['type'];
		}

		if ( isset( $features['dynamicContent'] ) && is_array( $features['dynamicContent'] )
			&& isset( $features['dynamicContent']['type'] ) && is_string( $features['dynamicContent']['type'] )
		) {
			return $features['dynamicContent']['type'];
		}

		if ( isset( $component['name'] ) && is_string( $component['name'] ) && '' !== $component['name'] ) {
			return $component['name'];
		}

		if ( isset( $component['type'] ) && is_string( $component['type'] ) && '' !== $component['type'] ) {
			return $component['type'];
		}

		return 'unknown';
	}

	/**
	 * @param array<string, mixed> $node Runtime schema fragment.
	 * @param array<int, string>   $targets Target keys.
	 * @return array<int, string>
	 */
	private static function discover_keys( array $node, array $targets ): array {
		$found = array();

		foreach ( $node as $key => $value ) {
			if ( is_string( $key ) && in_array( $key, $targets, true ) ) {
				$found[] = $key;
			}

			if ( is_array( $value ) ) {
				$found = array_merge( $found, self::discover_keys( $value, $targets ) );
			}
		}

		return array_values( array_unique( $found ) );
	}

	/**
	 * @param array<string, mixed> $node Runtime schema fragment.
	 * @param array<int, string>   $targets Target keys.
	 * @return array<int, string>
	 */
	private static function discover_scalar_list( array $node, array $targets ): array {
		foreach ( $node as $key => $value ) {
			if ( is_string( $key ) && in_array( $key, $targets, true ) && is_array( $value ) ) {
				return array_values(
					array_filter(
						$value,
						static function ( $item ): bool {
							return is_scalar( $item );
						}
					)
				);
			}

			if ( is_array( $value ) ) {
				$nested = self::discover_scalar_list( $value, $targets );

				if ( array() !== $nested ) {
					return $nested;
				}
			}
		}

		return array();
	}

	/**
	 * @param array<string, mixed> $node Runtime schema fragment.
	 * @param array<int, string>   $keys Feature keys.
	 */
	private static function feature_status( array $node, array $keys ): string {
		foreach ( $node as $key => $value ) {
			if ( is_string( $key ) && in_array( $key, $keys, true ) ) {
				if ( false === $value || null === $value ) {
					return 'unavailable';
				}

				return 'supported';
			}

			if ( is_array( $value ) ) {
				$status = self::feature_status( $value, $keys );

				if ( 'unknown' !== $status ) {
					return $status;
				}
			}
		}

		return 'unknown';
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
