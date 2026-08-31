<?php
/**
 * Generic runtime registry discovery for Divi 5.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

use Throwable;

final class RuntimeRegistryDiscovery {
	/**
	 * @var array<string, mixed>|null
	 */
	private static $snapshot = null;

	/**
	 * List runtime-derived registries without treating unknown as unsupported.
	 *
	 * @return array<string, mixed>
	 */
	public static function list_registries(): array {
		$snapshot   = self::snapshot();
		$registries = array();

		foreach ( $snapshot['registries'] as $name => $registry ) {
			$entries      = isset( $registry['entries'] ) && is_array( $registry['entries'] ) ? $registry['entries'] : array();
			$registries[] = array(
				'name'     => $name,
				'status'   => isset( $registry['status'] ) ? $registry['status'] : 'unknown',
				'count'    => count( $entries ),
				'evidence' => isset( $registry['evidence'] ) ? $registry['evidence'] : 'runtime discovery did not provide evidence',
			);
		}

		return array(
			'success'             => true,
			'runtime_fingerprint' => $snapshot['runtime_fingerprint'],
			'registries'          => $registries,
			'error_code'          => null,
			'error_message'       => null,
		);
	}

	/**
	 * Describe one discovered registry.
	 *
	 * @return array<string, mixed>
	 */
	public static function describe_registry( string $registry_name ): array {
		$snapshot = self::snapshot();

		if ( ! isset( $snapshot['registries'][ $registry_name ] ) ) {
			return array(
				'success'       => false,
				'error_code'    => 'registry_not_found',
				'error_message' => 'The requested registry is not exposed by the generic Divi runtime bridge.',
			);
		}

		$registry = $snapshot['registries'][ $registry_name ];

		return array(
			'success'             => true,
			'name'                => $registry_name,
			'status'              => isset( $registry['status'] ) ? $registry['status'] : 'unknown',
			'evidence'            => isset( $registry['evidence'] ) ? $registry['evidence'] : '',
			'entries'             => isset( $registry['entries'] ) ? $registry['entries'] : array(),
			'runtime_fingerprint' => $snapshot['runtime_fingerprint'],
			'error_code'          => null,
			'error_message'       => null,
		);
	}

	/**
	 * Return enabled/current runtime breakpoint names when discoverable.
	 *
	 * @return array<int, string>
	 */
	public static function breakpoint_names(): array {
		$snapshot = self::snapshot();
		$registry = isset( $snapshot['registries']['breakpoints'] ) ? $snapshot['registries']['breakpoints'] : array();
		$entries  = isset( $registry['entries'] ) && is_array( $registry['entries'] ) ? $registry['entries'] : array();
		$names    = array();

		foreach ( $entries as $entry ) {
			if ( is_array( $entry ) && isset( $entry['name'] ) && is_string( $entry['name'] ) && '' !== $entry['name'] ) {
				$names[] = $entry['name'];
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function fingerprint(): array {
		$snapshot = self::snapshot();
		return $snapshot['runtime_fingerprint'];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function snapshot(): array {
		if ( null !== self::$snapshot ) {
			return self::$snapshot;
		}

		$catalog = RuntimeModuleRegistry::catalog();
		$modules = isset( $catalog['modules'] ) && is_array( $catalog['modules'] ) ? $catalog['modules'] : array();

		$module_entries       = array();
		$field_components     = array();
		$option_groups        = array();
		$dynamic_content      = array();
		$states               = array();
		$attribute_roots      = array();
		$layout_engines       = array();
		$providers            = array();
		$parameter_signatures = array();
		$breakpoint_evidence  = array();

		foreach ( $modules as $module ) {
			if ( ! is_array( $module ) || ! isset( $module['name'] ) || ! is_string( $module['name'] ) ) {
				continue;
			}

			$name             = $module['name'];
			$module_entries[] = array(
				'name'               => $name,
				'title'              => isset( $module['title'] ) ? $module['title'] : $name,
				'provider'           => isset( $module['provider'] ) ? $module['provider'] : array(),
				'compatibility_mode' => isset( $module['compatibility_mode'] ) ? $module['compatibility_mode'] : 'unknown',
				'parameter_count'    => isset( $module['parameter_count'] ) ? $module['parameter_count'] : 0,
				'allowed_children'   => isset( $module['allowed_children'] ) ? $module['allowed_children'] : array(),
			);

			$provider = isset( $module['provider']['id'] ) && is_string( $module['provider']['id'] ) ? $module['provider']['id'] : 'unknown';
			if ( ! isset( $providers[ $provider ] ) ) {
				$providers[ $provider ] = array(
					'name'         => $provider,
					'module_count' => 0,
				);
			}
			++$providers[ $provider ]['module_count'];

			$parameters = isset( $module['parameter_graph'] ) && is_array( $module['parameter_graph'] ) ? $module['parameter_graph'] : array();
			foreach ( $parameters as $parameter ) {
				if ( ! is_array( $parameter ) ) {
					continue;
				}
				$parameter_signatures[] = array(
					'module'        => $name,
					'semantic_path' => isset( $parameter['semantic_path'] ) ? $parameter['semantic_path'] : null,
					'native_path'   => isset( $parameter['native_path'] ) ? $parameter['native_path'] : null,
					'runtime_hint'  => isset( $parameter['runtime_hint'] ) ? $parameter['runtime_hint'] : null,
					'type'          => isset( $parameter['type'] ) ? $parameter['type'] : 'unknown',
				);

				foreach ( array( 'devices', 'breakpoints' ) as $field ) {
					$values = isset( $parameter[ $field ] ) && is_array( $parameter[ $field ] ) ? $parameter[ $field ] : array();
					foreach ( $values as $value ) {
						if ( is_string( $value ) && '' !== $value ) {
							$breakpoint_evidence[ $value ] = true;
						}
					}
				}

				if ( isset( $parameter['hover'] ) && 'supported' === $parameter['hover'] ) {
					$states['hover'] = array(
						'name'   => 'hover',
						'source' => 'runtime-parameter-feature',
					);
				}
				if ( isset( $parameter['sticky'] ) && 'supported' === $parameter['sticky'] ) {
					$states['sticky'] = array(
						'name'   => 'sticky',
						'source' => 'runtime-parameter-feature',
					);
				}
			}

			$descriptor = RuntimeModuleRegistry::describe( $name );
			if ( empty( $descriptor['success'] ) || ! isset( $descriptor['raw_runtime']['attributes'] ) || ! is_array( $descriptor['raw_runtime']['attributes'] ) ) {
				continue;
			}

			$attributes = $descriptor['raw_runtime']['attributes'];
			foreach ( $attributes as $root => $schema ) {
				if ( ! is_string( $root ) ) {
					continue;
				}
				if ( ! isset( $attribute_roots[ $root ] ) ) {
					$attribute_roots[ $root ] = array(
						'name'         => $root,
						'module_count' => 0,
						'types'        => array(),
					);
				}
				++$attribute_roots[ $root ]['module_count'];
				if ( is_array( $schema ) && isset( $schema['type'] ) && is_string( $schema['type'] ) ) {
					$attribute_roots[ $root ]['types'][ $schema['type'] ] = true;
				}
			}

			self::scan_runtime_node(
				$attributes,
				$name,
				array(),
				$field_components,
				$option_groups,
				$dynamic_content,
				$states,
				$layout_engines,
				$breakpoint_evidence
			);

			if ( isset( $descriptor['raw_runtime']['default_attributes'] ) && is_array( $descriptor['raw_runtime']['default_attributes'] ) ) {
				self::scan_value_envelopes( $descriptor['raw_runtime']['default_attributes'], $breakpoint_evidence, $states );
			}
		}

		$breakpoint_registry = self::discover_breakpoint_registry( array_keys( $breakpoint_evidence ) );
		$breakpoints         = $breakpoint_registry['entries'];
		$states['value']     = array(
			'name'   => 'value',
			'source' => 'divi-attribute-state-convention',
		);

		foreach ( $attribute_roots as &$root_entry ) {
			$root_entry['types'] = array_values( array_keys( $root_entry['types'] ) );
		}
		unset( $root_entry );

		$registries = array(
			'modules'              => self::registry( $module_entries, 'runtime block registry' ),
			'providers'            => self::registry( array_values( $providers ), 'module namespaces observed in the runtime block registry' ),
			'field-components'     => self::registry( array_values( $field_components ), 'component metadata recursively discovered in runtime module schemas' ),
			'option-groups'        => self::registry( array_values( $option_groups ), 'groupSlug metadata recursively discovered in runtime module schemas' ),
			'breakpoints'          => array(
				'status'   => array() !== $breakpoints ? 'supported' : 'unknown',
				'evidence' => $breakpoint_registry['evidence'],
				'entries'  => $breakpoints,
			),
			'states'               => self::registry( array_values( $states ), 'runtime feature metadata and persisted breakpoint/state envelopes' ),
			'dynamic-content'      => self::registry( array_values( $dynamic_content ), 'features.dynamicContent metadata recursively discovered in runtime schemas' ),
			'attributes'           => self::registry( array_values( $attribute_roots ), 'top-level WordPress block attribute schemas registered by Divi modules' ),
			'layout-engines'       => self::registry( array_values( $layout_engines ), 'runtime option components and layout-related parameter paths' ),
			'presets'              => self::unknown_registry( 'preset capability is detected per parameter, but no authoritative runtime preset registry API was discovered by this adapter yet' ),
			'design-variables'     => self::unknown_registry( 'design-variable capability is detected per parameter, but no authoritative runtime variable registry API was discovered by this adapter yet' ),
			'loop-providers'       => self::unknown_registry( 'loop settings are visible in module schemas, but provider registration metadata is not yet exposed through a proven server-side registry' ),
			'interaction-triggers' => self::unknown_registry( 'interaction groups are visible in schemas, but trigger providers are not yet exposed through a proven server-side registry' ),
			'interaction-actions'  => self::unknown_registry( 'interaction groups are visible in schemas, but action providers are not yet exposed through a proven server-side registry' ),
		);

		$module_hash  = hash( 'sha256', self::canonical_json( $module_entries ) );
		$schema_hash  = hash( 'sha256', self::canonical_json( $parameter_signatures ) );
		$feature_hash = hash( 'sha256', self::canonical_json( $registries ) );

		self::$snapshot = array(
			'registries'          => $registries,
			'runtime_fingerprint' => array(
				'module_registry_hash'  => $module_hash,
				'schema_hash'           => $schema_hash,
				'feature_registry_hash' => $feature_hash,
				'breakpoint_hash'       => hash( 'sha256', self::canonical_json( $breakpoints ) ),
			),
		);

		return self::$snapshot;
	}

	/**
	 * @param array<string, mixed> $node Runtime schema node.
	 * @param array<int, string>   $path Current path.
	 * @param array<string, mixed> $field_components Component registry.
	 * @param array<string, mixed> $option_groups Option-group registry.
	 * @param array<string, mixed> $dynamic_content Dynamic content registry.
	 * @param array<string, mixed> $states State registry.
	 * @param array<string, mixed> $layout_engines Layout registry.
	 * @param array<string, bool>  $breakpoints Breakpoint evidence.
	 */
	private static function scan_runtime_node( array $node, string $module_name, array $path, array &$field_components, array &$option_groups, array &$dynamic_content, array &$states, array &$layout_engines, array &$breakpoints ): void {
		if ( isset( $node['component'] ) && is_array( $node['component'] ) ) {
			$component = $node['component'];
			$name      = isset( $component['name'] ) && is_string( $component['name'] ) ? $component['name'] : '';
			$type      = isset( $component['type'] ) && is_string( $component['type'] ) ? $component['type'] : 'unknown';
			if ( '' !== $name ) {
				$key = $type . ':' . $name;
				if ( ! isset( $field_components[ $key ] ) ) {
					$field_components[ $key ] = array(
						'name'           => $name,
						'component_type' => $type,
						'module_count'   => 0,
						'example_path'   => implode( '.', $path ),
					);
				}
				++$field_components[ $key ]['module_count'];

				if ( false !== stripos( $name, 'layout' ) || false !== stripos( implode( '.', $path ), 'layout' ) || false !== stripos( $name, 'grid' ) || false !== stripos( $name, 'flex' ) ) {
					$layout_engines[ $name ] = array(
						'name'           => $name,
						'component_type' => $type,
						'source'         => 'runtime-component',
					);
				}
			}
		}

		if ( isset( $node['groupSlug'] ) && is_string( $node['groupSlug'] ) && '' !== $node['groupSlug'] ) {
			$slug = $node['groupSlug'];
			if ( ! isset( $option_groups[ $slug ] ) ) {
				$option_groups[ $slug ] = array(
					'name'           => $slug,
					'module_count'   => 0,
					'example_module' => $module_name,
					'example_path'   => implode( '.', $path ),
				);
			}
			++$option_groups[ $slug ]['module_count'];
		}

		if ( isset( $node['features'] ) && is_array( $node['features'] ) ) {
			$features = $node['features'];
			if ( isset( $features['dynamicContent'] ) && is_array( $features['dynamicContent'] ) ) {
				$type                    = isset( $features['dynamicContent']['type'] ) && is_string( $features['dynamicContent']['type'] ) ? $features['dynamicContent']['type'] : 'unknown';
				$key                     = $type . ':' . $module_name . ':' . implode( '.', $path );
				$dynamic_content[ $key ] = array(
					'type'   => $type,
					'module' => $module_name,
					'path'   => implode( '.', $path ),
				);
			}
			foreach ( array( 'hover', 'sticky', 'active', 'focus' ) as $state ) {
				if ( isset( $features[ $state ] ) && false !== $features[ $state ] && null !== $features[ $state ] ) {
					$states[ $state ] = array(
						'name'   => $state,
						'source' => 'runtime-feature',
					);
				}
			}
		}

		foreach ( $node as $key => $value ) {
			if ( ! is_string( $key ) || ! is_array( $value ) ) {
				continue;
			}
			$next   = $path;
			$next[] = $key;
			self::scan_runtime_node( $value, $module_name, $next, $field_components, $option_groups, $dynamic_content, $states, $layout_engines, $breakpoints );
		}
	}

	/**
	 * Discover breakpoint/state envelopes from persisted/default attribute shapes.
	 *
	 * @param mixed                $value Value tree.
	 * @param array<string, bool>  $breakpoints Breakpoint evidence.
	 * @param array<string, mixed> $states State evidence.
	 */
	private static function scan_value_envelopes( $value, array &$breakpoints, array &$states ): void {
		if ( ! is_array( $value ) ) {
			return;
		}

		foreach ( $value as $key => $child ) {
			if ( is_string( $key ) && is_array( $child ) && array_key_exists( 'value', $child ) ) {
				$breakpoints[ $key ] = true;
				foreach ( $child as $state => $unused ) {
					if ( is_string( $state ) && 'value' !== $state ) {
						$states[ $state ] = array(
							'name'   => $state,
							'source' => 'persisted-attribute-envelope',
						);
					}
				}
			}
			self::scan_value_envelopes( $child, $breakpoints, $states );
		}
	}

	/**
	 * Discover Divi's breakpoint service by behavior rather than a hard-coded class name.
	 *
	 * @param array<int, string> $fallback_names Schema-derived breakpoint evidence.
	 * @return array<string, mixed>
	 */
	private static function discover_breakpoint_registry( array $fallback_names ): array {
		foreach ( get_declared_classes() as $class_name ) {
			if ( ! method_exists( $class_name, 'get_enabled_breakpoint_names' ) || ! method_exists( $class_name, 'get_base_breakpoint_name' ) ) {
				continue;
			}

			try {
				$enabled  = call_user_func( array( $class_name, 'get_enabled_breakpoint_names' ) );
				$base     = call_user_func( array( $class_name, 'get_base_breakpoint_name' ) );
				$order    = method_exists( $class_name, 'get_style_breakpoint_order' ) ? call_user_func( array( $class_name, 'get_style_breakpoint_order' ) ) : array();
				$settings = method_exists( $class_name, 'get_style_breakpoint_settings' ) ? call_user_func( array( $class_name, 'get_style_breakpoint_settings' ) ) : array();

				$names = array();
				if ( is_array( $order ) ) {
					foreach ( $order as $name ) {
						if ( is_string( $name ) ) {
							$names[] = $name;
						}
					}
				}
				if ( is_array( $enabled ) ) {
					foreach ( $enabled as $name ) {
						if ( is_string( $name ) ) {
							$names[] = $name;
						}
					}
				}
				$names = array_values( array_unique( $names ) );

				$entries = array();
				foreach ( $names as $position => $name ) {
					$entries[] = array(
						'name'       => $name,
						'base'       => is_string( $base ) && $name === $base,
						'enabled'    => is_array( $enabled ) ? in_array( $name, $enabled, true ) : null,
						'order'      => $position,
						'settings'   => is_array( $settings ) && isset( $settings[ $name ] ) ? $settings[ $name ] : null,
						'provenance' => 'runtime-breakpoint-service:' . $class_name,
					);
				}

				if ( array() !== $entries ) {
					return array(
						'entries'  => $entries,
						'evidence' => 'Divi breakpoint service discovered by method contract at runtime',
					);
				}
			} catch ( Throwable $throwable ) {
				continue;
			}
		}

		$fallback_names = array_values( array_unique( array_filter( $fallback_names, 'is_string' ) ) );
		$entries        = array();
		foreach ( $fallback_names as $position => $name ) {
			$entries[] = array(
				'name'       => $name,
				'base'       => null,
				'enabled'    => null,
				'order'      => $position,
				'settings'   => null,
				'provenance' => 'runtime-schema-or-persisted-envelope',
			);
		}

		return array(
			'entries'  => $entries,
			'evidence' => array() !== $entries ? 'breakpoint names inferred only from runtime schema/default envelopes' : 'runtime did not expose breakpoint service or breakpoint-shaped values',
		);
	}

	/**
	 * @param array<int, mixed> $entries Registry entries.
	 * @return array<string, mixed>
	 */
	private static function registry( array $entries, string $evidence ): array {
		return array(
			'status'   => array() !== $entries ? 'supported' : 'unknown',
			'evidence' => $evidence,
			'entries'  => $entries,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function unknown_registry( string $evidence ): array {
		return array(
			'status'   => 'unknown',
			'evidence' => $evidence,
			'entries'  => array(),
		);
	}

	/**
	 * Stable JSON encoding for hash inputs.
	 *
	 * @param mixed $value Value.
	 */
	private static function canonical_json( $value ): string {
		$encoded = wp_json_encode( $value );
		return is_string( $encoded ) ? $encoded : '';
	}

	private function __construct() {
	}
}
